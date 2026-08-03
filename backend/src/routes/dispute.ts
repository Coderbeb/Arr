import { FastifyPluginAsync, FastifyRequest, FastifyReply } from 'fastify';
import { query, transaction } from '../db';
import { getIO } from '../socketInstance';
import { saveFile } from '../services/fileUpload';
import { analyzeFile, analyzeDispute } from '../services/proofAnalyzer';

async function requireAuth(req: FastifyRequest, reply: FastifyReply) {
  try { await req.jwtVerify(); }
  catch { return reply.status(401).send({ error: 'Unauthorized' }); }
}

export const disputeRoutes: FastifyPluginAsync = async (fastify) => {
  // ── BUYER APPEAL (after seller_rejected) ─────────────────────
  fastify.post('/appeal/:trade_id', { preHandler: [requireAuth] }, async (req: FastifyRequest, reply: FastifyReply) => {
    const { id: buyer_id } = req.user as any;
    const { trade_id } = req.params as any;

    // Verify this trade belongs to the buyer and is in seller_rejected state
    const tradeResult = await query(
      `SELECT t.*, d.id as dispute_id, d.status as dispute_status, d.proof_deadline
       FROM trades t
       LEFT JOIN disputes d ON d.trade_id = t.id
       WHERE t.id = $1 AND t.buyer_id = $2 AND t.status = 'seller_rejected'
       ORDER BY d.created_at DESC LIMIT 1`,
      [trade_id, buyer_id]
    );
    if (tradeResult.rows.length === 0) {
      return reply.status(404).send({ error: 'Trade not found or not eligible for appeal' });
    }
    const trade = tradeResult.rows[0];
    const dispute_id = trade.dispute_id;

    if (!dispute_id) {
      return reply.status(400).send({ error: 'No dispute found for this trade' });
    }

    // Check proof deadline
    if (trade.proof_deadline && new Date() > new Date(trade.proof_deadline)) {
      return reply.status(400).send({ error: 'Appeal deadline has passed' });
    }

    // Parse multipart files (screen_recording + bank_statement)
    const files: Record<string, { url: string; hash: string; size: number; mime: string; buffer: Buffer }> = {};
    const parts = req.parts();
    for await (const part of parts) {
      if (part.type === 'file') {
        const buffer = await part.toBuffer();
        const result = await saveFile(
          buffer, part.filename || 'proof', part.mimetype,
          'disputes', dispute_id, `buyer_${part.fieldname}`
        );
        files[part.fieldname] = {
          url: result.url, hash: result.hash,
          size: result.size, mime: result.mimeType, buffer,
        };
      }
    }

    if (Object.keys(files).length === 0) {
      return reply.status(400).send({ error: 'At least one proof file is required (bank statement or screen recording).' });
    }

    // Update dispute with buyer's files
    await query(
      `UPDATE disputes
       SET buyer_screen_recording_url = COALESCE($1, buyer_screen_recording_url),
           buyer_bank_statement_url = COALESCE($2, buyer_bank_statement_url),
           buyer_screenshot_url = COALESCE($3, buyer_screenshot_url),
           buyer_proof_submitted_at = NOW(), status = 'under_review'
       WHERE id = $4`,
      [
        files['screen_recording']?.url || null,
        files['bank_statement']?.url || null,
        files['screenshot']?.url || null,
        dispute_id,
      ]
    );

    // Move trade status from seller_rejected → disputed
    await transaction(async (client) => {
      await client.query(`UPDATE trades SET status = 'disputed' WHERE id = $1`, [trade_id]);
    });

    // Analyze buyer's primary proof file
    const primaryFile = files['screen_recording'] || files['bank_statement'] || files['screenshot'];
    if (primaryFile) {
      analyzeFile(primaryFile.buffer, primaryFile.hash, primaryFile.mime, primaryFile.size, buyer_id)
        .then(async (analysis) => {
          await query(
            `UPDATE disputes SET buyer_ai_score = $1, buyer_ai_breakdown = $2, buyer_proof_analysis = $3 WHERE id = $4`,
            [analysis.score, JSON.stringify(analysis.breakdown), JSON.stringify(analysis), dispute_id]
          );
          try { getIO().emit('dispute:scored', { dispute_id, side: 'buyer', score: analysis.score }); } catch {}

          // If both sides have submitted, generate comparative recommendation
          const updatedDispute = await query('SELECT buyer_ai_score, seller_ai_score, seller_proof_analysis FROM disputes WHERE id = $1', [dispute_id]);
          const d = updatedDispute.rows[0];
          if (d.buyer_ai_score != null && d.seller_ai_score != null) {
            const sellerAnalysis = d.seller_proof_analysis ? JSON.parse(JSON.stringify(d.seller_proof_analysis)) : null;
            const comparison = await analyzeDispute(analysis, sellerAnalysis);
            await query(
              `UPDATE disputes SET ai_recommendation = $1, ai_confidence = $2 WHERE id = $3`,
              [comparison.recommendation, comparison.confidence, dispute_id]
            );
            try {
              getIO().emit('dispute:recommendation', {
                dispute_id, recommendation: comparison.recommendation,
                confidence: comparison.confidence, reasoning: comparison.reasoning,
              });
            } catch {}
          }
        })
        .catch(console.error);
    }

    return reply.send({ dispute_id, message: 'Appeal submitted with proof. AI analysis in progress. An assistant will review shortly.' });
  });
  // ── UPLOAD BUYER PROOF ───────────────────────────────────────
  fastify.post('/buyer-proof/:dispute_id', { preHandler: [requireAuth] }, async (req: FastifyRequest, reply: FastifyReply) => {
    const { id: user_id } = req.user as any;
    const { dispute_id } = req.params as any;

    const disputeResult = await query(
      `SELECT d.*, t.buyer_id, t.amount FROM disputes d
       JOIN trades t ON d.trade_id = t.id
       WHERE d.id = $1 AND d.status IN ('pending', 'under_review')`,
      [dispute_id]
    );
    if (disputeResult.rows.length === 0) return reply.status(404).send({ error: 'Dispute not found or already resolved' });
    const dispute = disputeResult.rows[0];
    if (dispute.buyer_id !== user_id) return reply.status(403).send({ error: 'Only the buyer can upload buyer proof' });
    if (new Date() > new Date(dispute.proof_deadline)) return reply.status(400).send({ error: 'Proof deadline has passed' });

    // Process multipart files — real file saving
    const files: Record<string, { url: string; hash: string; size: number; mime: string; buffer: Buffer }> = {};
    const parts = req.parts();
    for await (const part of parts) {
      if (part.type === 'file') {
        const buffer = await part.toBuffer();
        const result = await saveFile(
          buffer, part.filename || 'proof', part.mimetype,
          'disputes', dispute_id, `buyer_${part.fieldname}`
        );
        files[part.fieldname] = {
          url: result.url, hash: result.hash,
          size: result.size, mime: result.mimeType, buffer,
        };
      }
    }

    if (Object.keys(files).length === 0) {
      return reply.status(400).send({ error: 'At least one proof file is required (bank statement or screen recording).' });
    }

    // Update dispute with file URLs
    await query(
      `UPDATE disputes
       SET buyer_screenshot_url = COALESCE($1, buyer_screenshot_url),
           buyer_screen_recording_url = COALESCE($2, buyer_screen_recording_url),
           buyer_bank_statement_url = COALESCE($3, buyer_bank_statement_url),
           buyer_proof_submitted_at = NOW(), status = 'under_review'
       WHERE id = $4`,
      [
        files['screenshot']?.url || null,
        files['screen_recording']?.url || null,
        files['bank_statement']?.url || null,
        dispute_id,
      ]
    );

    // Analyze buyer's primary proof file (pick the best available)
    const primaryFile = files['screen_recording'] || files['bank_statement'] || files['screenshot'];
    if (primaryFile) {
      analyzeFile(primaryFile.buffer, primaryFile.hash, primaryFile.mime, primaryFile.size, user_id)
        .then(async (analysis) => {
          await query(
            `UPDATE disputes SET buyer_ai_score = $1, buyer_ai_breakdown = $2, buyer_proof_analysis = $3 WHERE id = $4`,
            [analysis.score, JSON.stringify(analysis.breakdown), JSON.stringify(analysis), dispute_id]
          );
          try { getIO().emit('dispute:scored', { dispute_id, side: 'buyer', score: analysis.score }); } catch {}

          // If both sides have submitted, generate comparative recommendation
          const updatedDispute = await query('SELECT buyer_ai_score, seller_ai_score, seller_proof_analysis FROM disputes WHERE id = $1', [dispute_id]);
          const d = updatedDispute.rows[0];
          if (d.buyer_ai_score != null && d.seller_ai_score != null) {
            const sellerAnalysis = d.seller_proof_analysis ? JSON.parse(JSON.stringify(d.seller_proof_analysis)) : null;
            const comparison = await analyzeDispute(analysis, sellerAnalysis);
            await query(
              `UPDATE disputes SET ai_recommendation = $1, ai_confidence = $2 WHERE id = $3`,
              [comparison.recommendation, comparison.confidence, dispute_id]
            );
            try {
              getIO().emit('dispute:recommendation', {
                dispute_id, recommendation: comparison.recommendation,
                confidence: comparison.confidence, reasoning: comparison.reasoning,
              });
            } catch {}
          }
        })
        .catch(console.error);
    }

    return reply.send({ message: 'Buyer proof uploaded. AI analysis in progress.' });
  });

  // ── UPLOAD SELLER PROOF ──────────────────────────────────────
  fastify.post('/seller-proof/:dispute_id', { preHandler: [requireAuth] }, async (req: FastifyRequest, reply: FastifyReply) => {
    const { id: user_id } = req.user as any;
    const { dispute_id } = req.params as any;

    const disputeResult = await query(
      `SELECT d.*, t.seller_id, t.amount FROM disputes d
       JOIN trades t ON d.trade_id = t.id
       WHERE d.id = $1 AND d.status IN ('pending', 'under_review')`,
      [dispute_id]
    );
    if (disputeResult.rows.length === 0) return reply.status(404).send({ error: 'Dispute not found' });
    const dispute = disputeResult.rows[0];
    if (dispute.seller_id !== user_id) return reply.status(403).send({ error: 'Only the seller can upload seller proof' });
    if (new Date() > new Date(dispute.proof_deadline)) return reply.status(400).send({ error: 'Proof deadline has passed' });

    const files: Record<string, { url: string; hash: string; size: number; mime: string; buffer: Buffer }> = {};
    const parts = req.parts();
    for await (const part of parts) {
      if (part.type === 'file') {
        const buffer = await part.toBuffer();
        const result = await saveFile(
          buffer, part.filename || 'proof', part.mimetype,
          'disputes', dispute_id, `seller_${part.fieldname}`
        );
        files[part.fieldname] = {
          url: result.url, hash: result.hash,
          size: result.size, mime: result.mimeType, buffer,
        };
      }
    }

    if (Object.keys(files).length === 0) {
      return reply.status(400).send({ error: 'At least one proof file is required.' });
    }

    await query(
      `UPDATE disputes
       SET seller_screen_recording_url = COALESCE($1, seller_screen_recording_url),
           seller_txn_screenshot_url = COALESCE($2, seller_txn_screenshot_url),
           seller_proof_submitted_at = NOW()
       WHERE id = $3`,
      [files['screen_recording']?.url || null, files['txn_screenshot']?.url || null, dispute_id]
    );

    // Analyze seller proof
    const primaryFile = files['screen_recording'] || files['txn_screenshot'];
    if (primaryFile) {
      analyzeFile(primaryFile.buffer, primaryFile.hash, primaryFile.mime, primaryFile.size, user_id)
        .then(async (analysis) => {
          await query(
            `UPDATE disputes SET seller_ai_score = $1, seller_ai_breakdown = $2, seller_proof_analysis = $3 WHERE id = $4`,
            [analysis.score, JSON.stringify(analysis.breakdown), JSON.stringify(analysis), dispute_id]
          );
          try { getIO().emit('dispute:scored', { dispute_id, side: 'seller', score: analysis.score }); } catch {}

          // If both sides have submitted, generate comparative recommendation
          const updatedDispute = await query('SELECT buyer_ai_score, seller_ai_score, buyer_proof_analysis FROM disputes WHERE id = $1', [dispute_id]);
          const d = updatedDispute.rows[0];
          if (d.buyer_ai_score != null && d.seller_ai_score != null) {
            const buyerAnalysis = d.buyer_proof_analysis ? JSON.parse(JSON.stringify(d.buyer_proof_analysis)) : null;
            const comparison = await analyzeDispute(buyerAnalysis, analysis);
            await query(
              `UPDATE disputes SET ai_recommendation = $1, ai_confidence = $2 WHERE id = $3`,
              [comparison.recommendation, comparison.confidence, dispute_id]
            );
            try {
              getIO().emit('dispute:recommendation', {
                dispute_id, recommendation: comparison.recommendation,
                confidence: comparison.confidence, reasoning: comparison.reasoning,
              });
            } catch {}
          }
        })
        .catch(console.error);
    }

    return reply.send({ message: 'Seller proof uploaded. AI analysis in progress.' });
  });

  // ── GET DISPUTE DETAILS (enhanced with analysis) ─────────────
  fastify.get('/:dispute_id', { preHandler: [requireAuth] }, async (req: FastifyRequest, reply: FastifyReply) => {
    const { id: user_id } = req.user as any;
    const { dispute_id } = req.params as any;

    const result = await query(
      `SELECT d.*,
              t.amount, t.utr_number, t.buyer_payment_screenshot_url,
              buyer.full_name as buyer_name, buyer.mobile_number as buyer_mobile,
              seller.full_name as seller_name, seller.mobile_number as seller_mobile
       FROM disputes d
       JOIN trades t ON d.trade_id = t.id
       JOIN users buyer ON t.buyer_id = buyer.id
       JOIN users seller ON t.seller_id = seller.id
       WHERE d.id = $1 AND (t.buyer_id = $2 OR t.seller_id = $2)`,
      [dispute_id, user_id]
    );
    if (result.rows.length === 0) return reply.status(404).send({ error: 'Dispute not found' });
    return reply.send(result.rows[0]);
  });
};
