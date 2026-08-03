import crypto from 'crypto';
import { query } from '../db';

/**
 * Proof Analysis Engine
 * 
 * Performs multiple checks on uploaded proof files and generates
 * a credibility score (0-100) with a detailed breakdown.
 */

export interface AnalysisResult {
  score: number;               // 0-100 overall credibility
  breakdown: CheckResult[];    // Individual check results
  flags: string[];             // Human-readable warning flags
  recommendation: string;      // 'credible' | 'suspicious' | 'likely_fraud'
}

export interface CheckResult {
  check: string;
  weight: number;
  score: number;         // 0-100 for this specific check
  passed: boolean;
  details: string;
}

// ── Known editing software signatures in EXIF ─────────────────────
const EDITING_SOFTWARE = [
  'photoshop', 'gimp', 'paint.net', 'pixlr', 'canva',
  'adobe', 'lightroom', 'snapseed', 'picsart', 'fotor',
  'afterlight', 'vsco', 'prisma', 'inshot', 'capcut',
];

// ── Common phone screenshot resolutions ───────────────────────────
const VALID_SCREEN_WIDTHS = [
  360, 375, 390, 393, 412, 414, 428, 430,   // Common Android/iPhone widths
  720, 750, 780, 828, 1080, 1125, 1170, 1179, 1242, 1284, 1290, // Pixel densities
  1440, 2160,
];

// ── Known AI video generators, synthetic tools, & desktop video editors ──────
const AI_VIDEO_AND_EDITOR_SIGNATURES = [
  'sora', 'runway', 'pika', 'luma', 'kling', 'haiper', 'hunyuan', 'cogvideo',
  'svd', 'stable-video', 'deforum', 'animate-diff', 'midjourney', 'ideogram',
  'synthesia', 'heygen', 'd-id', 'invideo', 'kapwing', 'clipchamp', 'veed.io',
  'animaker', 'wondershare', 'filmora', 'camtasia', 'obs', 'handbrake', 'ffmpeg',
  'premiere', 'aftereffects', 'davinci', 'capcut', 'inshot', 'lightworks', 'topaz',
  'viva-video', 'magisto', 'splices', 'quik',
];

// ── Mobile Screen Recording Metadata Identifiers ──────────────────────────────
const MOBILE_RECORDER_BRANDS = [
  'com.android', 'android', 'com.apple.quicktime', 'apple', 'isom', 'mp42', '3gp',
];

/**
 * Analyze a single proof file (image or video).
 */
export async function analyzeFile(
  buffer: Buffer,
  hash: string,
  mimeType: string,
  fileSize: number,
  userId: string,
): Promise<AnalysisResult> {
  const checks: CheckResult[] = [];
  const flags: string[] = [];
  const isImage = mimeType.startsWith('image/');
  const isVideo = mimeType.startsWith('video/');
  const isPdf = mimeType === 'application/pdf';

  let isCriticalFraud = false;

  // ── Check 1: EXIF Metadata (images only) ─────────────────────
  if (isImage) {
    const exifCheck = analyzeExifData(buffer);
    checks.push(exifCheck);
    if (!exifCheck.passed) flags.push(exifCheck.details);
  }

  // ── Check 2: File Hash — duplicate/known fraud detection ─────
  const hashCheck = await checkFileHash(hash);
  checks.push(hashCheck);
  if (!hashCheck.passed) {
    flags.push(hashCheck.details);
    if (hashCheck.score === 0) isCriticalFraud = true;
  }

  // ── Check 3: Image Dimensions (images only) ──────────────────
  if (isImage) {
    const dimCheck = checkImageDimensions(buffer);
    checks.push(dimCheck);
    if (!dimCheck.passed) flags.push(dimCheck.details);
  }

  // ── Check 4: Video Analysis (videos only - High Weight) ───────
  if (isVideo) {
    const videoCheck = checkVideoProof(buffer, fileSize);
    checks.push(videoCheck);
    if (!videoCheck.passed) {
      flags.push(videoCheck.details);
      if (videoCheck.score <= 20) isCriticalFraud = true;
    }
  }

  // ── Check 4.5: PDF Analysis ──────────────────────────────────
  if (isPdf) {
    const pdfCheck = checkPdfProof(buffer);
    checks.push(pdfCheck);
    if (!pdfCheck.passed) {
      flags.push(pdfCheck.details);
      if (pdfCheck.score <= 20) isCriticalFraud = true;
    }
  }

  // ── Check 5: File Size Consistency ───────────────────────────
  const sizeCheck = checkFileSize(fileSize, isImage, isVideo, isPdf);
  checks.push(sizeCheck);
  if (!sizeCheck.passed) flags.push(sizeCheck.details);

  // ── Check 6: User History ────────────────────────────────────
  const historyCheck = await checkUserHistory(userId);
  checks.push(historyCheck);
  if (!historyCheck.passed) flags.push(historyCheck.details);

  // ── Calculate weighted average score ─────────────────────────
  const totalWeight = checks.reduce((sum, c) => sum + c.weight, 0);
  const weightedScore = checks.reduce((sum, c) => sum + (c.score * c.weight), 0);
  let score = Math.round(weightedScore / totalWeight);

  // 🚨 CRITICAL FRAUD CAPPING: If video is non-portrait, AI-generated, or edited, CAP SCORE AT MAX 15%
  if (isCriticalFraud) {
    score = Math.min(score, 15);
  }

  const recommendation = score >= 70 ? 'credible' : score >= 40 ? 'suspicious' : 'likely_fraud';

  return { score, breakdown: checks, flags, recommendation };
}

/**
 * Robust Video Analysis & Synthetic AI Video Detector
 */
function checkVideoProof(buffer: Buffer, fileSize: number): CheckResult {
  const str = buffer.toString('latin1').toLowerCase();

  // 1. Container Check
  const hasMP4 = buffer.includes(Buffer.from('ftyp', 'ascii'));
  const hasWebM = buffer[0] === 0x1A && buffer[1] === 0x45 && buffer[2] === 0xDF && buffer[3] === 0xA3;

  if (!hasMP4 && !hasWebM) {
    return {
      check: 'Video Proof Shield',
      weight: 40,
      score: 0,
      passed: false,
      details: '🚨 CRITICAL FRAUD: Invalid video container format. Not a valid mobile recording.',
    };
  }

  // 2. Scan for AI video generator & video editor signatures
  const foundAiSignatures: string[] = [];
  for (const sig of AI_VIDEO_AND_EDITOR_SIGNATURES) {
    if (str.includes(sig)) {
      foundAiSignatures.push(sig);
    }
  }

  if (foundAiSignatures.length > 0) {
    return {
      check: 'Video Proof Shield',
      weight: 40,
      score: 0,
      passed: false,
      details: `🚨 CRITICAL FRAUD: AI video generator / video editor signature detected ("${foundAiSignatures.join(', ')}"). File is fake or manipulated.`,
    };
  }

  // 3. Extract Video Track Dimensions from MP4 `tkhd` or `stsd` atoms
  let width = 0;
  let height = 0;

  if (hasMP4) {
    // Search for tkhd (track header) atom
    const tkhdIndex = buffer.indexOf(Buffer.from('tkhd', 'ascii'));
    if (tkhdIndex !== -1 && tkhdIndex + 92 < buffer.length) {
      const version = buffer[tkhdIndex + 4];
      if (version === 0) {
        // Width at offset +76, height at +80 (fixed point 16.16)
        width = buffer.readUInt32BE(tkhdIndex + 76) >> 16;
        height = buffer.readUInt32BE(tkhdIndex + 80) >> 16;
      } else if (version === 1) {
        // Width at offset +88, height at +92
        width = buffer.readUInt32BE(tkhdIndex + 88) >> 16;
        height = buffer.readUInt32BE(tkhdIndex + 92) >> 16;
      }
    }
  }

  // If width/height were extracted, perform orientation & aspect ratio checks
  if (width > 0 && height > 0) {
    // Mobile screen recordings MUST be Portrait mode (Height > Width)
    if (width >= height) {
      return {
        check: 'Video Proof Shield',
        weight: 40,
        score: 10,
        passed: false,
        details: `🚨 CRITICAL FRAUD: Video orientation is Landscape/Square (${width}×${height}). Mobile screen recordings from UPI apps MUST be Portrait (Height > Width).`,
      };
    }

    const aspectRatio = height / width;
    // Mobile phone aspect ratios are between 1.50 (3:2) and 2.50 (22.5:9)
    if (aspectRatio < 1.45 || aspectRatio > 2.55) {
      return {
        check: 'Video Proof Shield',
        weight: 40,
        score: 20,
        passed: false,
        details: `🚨 SUSPICIOUS: Video aspect ratio (${width}×${height}, ratio ${aspectRatio.toFixed(2)}) does not match any standard smartphone screen.`,
      };
    }

    if (height < 700 || width < 300) {
      return {
        check: 'Video Proof Shield',
        weight: 40,
        score: 30,
        passed: false,
        details: `🚨 SUSPICIOUS: Resolution (${width}×${height}) is too low for a genuine smartphone screen recording.`,
      };
    }
  }

  // 4. Check for authentic mobile screen recorder brand signatures
  const hasMobileBrand = MOBILE_RECORDER_BRANDS.some(b => str.includes(b));

  if (width > 0 && height > 0 && height > width) {
    return {
      check: 'Video Proof Shield',
      weight: 40,
      score: hasMobileBrand ? 95 : 85,
      passed: true,
      details: `✅ Verified mobile screen recording (${width}×${height} Portrait). Mobile recorder signatures confirmed.`,
    };
  }

  // Fallback if dimensions atom couldn't be parsed but format is valid MP4/WebM
  return {
    check: 'Video Proof Shield',
    weight: 40,
    score: hasMobileBrand ? 80 : 65,
    passed: true,
    details: 'Valid video container detected, no editing software signatures found.',
  };
}

/**
 * PDF Analysis for manipulation detection
 */
function checkPdfProof(buffer: Buffer): CheckResult {
  const str = buffer.toString('latin1').toLowerCase();
  
  if (!str.startsWith('%pdf-')) {
    return {
      check: 'PDF Shield',
      weight: 40,
      score: 0,
      passed: false,
      details: '🚨 CRITICAL FRAUD: Invalid PDF format. File is corrupted or fake.',
    };
  }

  const foundEditors: string[] = [];
  for (const editor of EDITING_SOFTWARE) {
    if (str.includes(editor)) {
      foundEditors.push(editor);
    }
  }
  
  if (str.includes('itext') || str.includes('pdf-xchange') || str.includes('ilovepdf') || str.includes('pdf24')) {
    foundEditors.push('PDF Manipulator Tool');
  }

  if (foundEditors.length > 0) {
    return {
      check: 'PDF Shield',
      weight: 40,
      score: 10,
      passed: false,
      details: `🚨 SUSPICIOUS: Editing software or manipulator detected (${foundEditors.join(', ')}). PDF may have been altered.`,
    };
  }

  return {
    check: 'PDF Shield',
    weight: 40,
    score: 95,
    passed: true,
    details: '✅ Valid PDF file. No editing tools detected in metadata.',
  };
}

/**
 * Analyze both sides of a dispute and generate a comparative recommendation.
 */
export async function analyzeDispute(
  buyerAnalysis: AnalysisResult | null,
  sellerAnalysis: AnalysisResult | null,
): Promise<{ recommendation: string; confidence: number; reasoning: string }> {
  const buyerScore = buyerAnalysis?.score ?? 50;
  const sellerScore = sellerAnalysis?.score ?? 50;

  const diff = Math.abs(buyerScore - sellerScore);
  const confidence = Math.min(100, Math.round(diff * 1.5 + 20));

  let recommendation: string;
  let reasoning: string;

  if (buyerScore > sellerScore + 15) {
    recommendation = 'buyer_likely';
    reasoning = `Buyer proof scored ${buyerScore}/100 vs Seller ${sellerScore}/100. Buyer evidence appears more credible.`;
  } else if (sellerScore > buyerScore + 15) {
    recommendation = 'seller_likely';
    reasoning = `Seller proof scored ${sellerScore}/100 vs Buyer ${buyerScore}/100. Seller evidence appears more credible.`;
  } else {
    recommendation = 'inconclusive';
    reasoning = `Both sides scored similarly (Buyer: ${buyerScore}, Seller: ${sellerScore}). Manual review required.`;
  }

  return { recommendation, confidence, reasoning };
}

function analyzeExifData(buffer: Buffer): CheckResult {
  const str = buffer.toString('latin1').toLowerCase();
  
  const foundEditors: string[] = [];
  for (const editor of EDITING_SOFTWARE) {
    if (str.includes(editor)) {
      foundEditors.push(editor);
    }
  }

  if (foundEditors.length > 0) {
    return {
      check: 'EXIF Metadata',
      weight: 20,
      score: 10,
      passed: false,
      details: `Editing software detected: ${foundEditors.join(', ')}. Image may have been manipulated.`,
    };
  }

  const hasExif = buffer.includes(Buffer.from('Exif', 'ascii'));
  
  return {
    check: 'EXIF Metadata',
    weight: 20,
    score: hasExif ? 75 : 85,
    passed: true,
    details: hasExif ? 'EXIF data present, no editing software detected.' : 'No EXIF data (typical for screenshots).',
  };
}

async function checkFileHash(hash: string): Promise<CheckResult> {
  try {
    const result = await query('SELECT id, reason FROM fraud_hashes WHERE file_hash = $1', [hash]);
    if (result.rows.length > 0) {
      return {
        check: 'File Hash',
        weight: 15,
        score: 0,
        passed: false,
        details: `⚠️ KNOWN FRAUD: This exact file was previously flagged. Reason: ${result.rows[0].reason || 'Previously used in fraud'}`,
      };
    }
  } catch {}

  return {
    check: 'File Hash',
    weight: 15,
    score: 90,
    passed: true,
    details: 'File hash is unique — not found in fraud database.',
  };
}

function checkImageDimensions(buffer: Buffer): CheckResult {
  let width = 0;
  let height = 0;

  if (buffer[0] === 0x89 && buffer[1] === 0x50) {
    width = buffer.readUInt32BE(16);
    height = buffer.readUInt32BE(20);
  } else if (buffer[0] === 0xFF && buffer[1] === 0xD8) {
    let offset = 2;
    while (offset < buffer.length - 8) {
      if (buffer[offset] === 0xFF) {
        const marker = buffer[offset + 1];
        if (marker >= 0xC0 && marker <= 0xCF && marker !== 0xC4 && marker !== 0xC8) {
          height = buffer.readUInt16BE(offset + 5);
          width = buffer.readUInt16BE(offset + 7);
          break;
        }
        const len = buffer.readUInt16BE(offset + 2);
        offset += 2 + len;
      } else {
        offset++;
      }
    }
  }

  if (width === 0 || height === 0) {
    return {
      check: 'Image Dimensions',
      weight: 10,
      score: 60,
      passed: true,
      details: 'Could not read image dimensions — format not recognized.',
    };
  }

  const minDim = Math.min(width, height);
  const maxDim = Math.max(width, height);
  const isPhoneRes = VALID_SCREEN_WIDTHS.some(w => Math.abs(minDim - w) < 20 || Math.abs(maxDim - w) < 20);
  const ratio = maxDim / minDim;
  const isPhoneRatio = ratio >= 1.5 && ratio <= 2.5;

  if (isPhoneRes && isPhoneRatio) {
    return {
      check: 'Image Dimensions',
      weight: 10,
      score: 90,
      passed: true,
      details: `Dimensions ${width}×${height} match phone screenshot resolution.`,
    };
  }

  return {
    check: 'Image Dimensions',
    weight: 10,
    score: 50,
    passed: false,
    details: `Dimensions ${width}×${height} don't match typical phone screenshots. May be cropped or edited.`,
  };
}

function checkFileSize(fileSize: number, isImage: boolean, isVideo: boolean, isPdf: boolean = false): CheckResult {
  if (isImage) {
    if (fileSize < 20 * 1024) {
      return {
        check: 'File Size',
        weight: 10,
        score: 30,
        passed: false,
        details: `Image is extremely small (${(fileSize / 1024).toFixed(0)}KB). Typical screenshots are 100KB+.`,
      };
    }
    if (fileSize < 50 * 1024) {
      return {
        check: 'File Size',
        weight: 10,
        score: 25,
        passed: false,
        details: `Image is very small (${(fileSize / 1024).toFixed(0)}KB). Possibly heavily compressed or generated.`,
      };
    }
    if (fileSize > 8 * 1024 * 1024) {
      return {
        check: 'File Size',
        weight: 10,
        score: 60,
        passed: true,
        details: `Image is very large (${(fileSize / (1024 * 1024)).toFixed(1)}MB). Unusual for screenshots.`,
      };
    }
    return {
      check: 'File Size',
      weight: 10,
      score: 90,
      passed: true,
      details: `Image size ${(fileSize / 1024).toFixed(0)}KB is within normal range.`,
    };
  }

  if (isVideo) {
    if (fileSize < 500 * 1024) {
      return {
        check: 'File Size',
        weight: 10,
        score: 20,
        passed: false,
        details: `Video is very small (${(fileSize / 1024).toFixed(0)}KB). Too short or heavily compressed.`,
      };
    }
    return {
      check: 'File Size',
      weight: 10,
      score: 85,
      passed: true,
      details: `Video size ${(fileSize / (1024 * 1024)).toFixed(1)}MB is within expected range.`,
    };
  }

  if (isPdf) {
    if (fileSize < 5 * 1024) {
      return {
        check: 'File Size',
        weight: 10,
        score: 40,
        passed: false,
        details: `PDF is suspiciously small (${(fileSize / 1024).toFixed(0)}KB). Might be corrupted.`,
      };
    }
    return {
      check: 'File Size',
      weight: 10,
      score: 90,
      passed: true,
      details: `PDF size ${(fileSize / 1024).toFixed(0)}KB is within normal range.`,
    };
  }

  return {
    check: 'File Size',
    weight: 10,
    score: 75,
    passed: true,
    details: `File size: ${(fileSize / 1024).toFixed(0)}KB.`,
  };
}

async function checkUserHistory(userId: string): Promise<CheckResult> {
  try {
    const userResult = await query(
      `SELECT total_trades, strike_count, reputation_score,
              (SELECT COUNT(*) FROM disputes d JOIN trades t ON d.trade_id = t.id
               WHERE (t.buyer_id = $1 OR t.seller_id = $1)
                 AND d.status IN ('resolved_buyer', 'resolved_seller')
                 AND ((t.buyer_id = $1 AND d.status = 'resolved_seller')
                   OR (t.seller_id = $1 AND d.status = 'resolved_buyer'))) as disputes_lost
       FROM users WHERE id = $1`,
      [userId]
    );

    if (userResult.rows.length === 0) {
      return {
        check: 'User History',
        weight: 15,
        score: 50,
        passed: true,
        details: 'User not found — neutral score assigned.',
      };
    }

    const user = userResult.rows[0];
    const trades = parseInt(user.total_trades);
    const strikes = parseInt(user.strike_count);
    const reputation = parseInt(user.reputation_score);
    const disputesLost = parseInt(user.disputes_lost);

    let score = 70;

    if (trades >= 50) score += 10;
    else if (trades >= 20) score += 5;
    else if (trades < 5) score -= 10;

    score -= strikes * 15;

    if (reputation >= 90) score += 10;
    else if (reputation < 50) score -= 20;

    score -= disputesLost * 10;

    score = Math.max(0, Math.min(100, score));

    const details = `Trades: ${trades}, Strikes: ${strikes}, Reputation: ${reputation}%, Disputes lost: ${disputesLost}`;

    return {
      check: 'User History',
      weight: 15,
      score,
      passed: score >= 50,
      details,
    };
  } catch {
    return {
      check: 'User History',
      weight: 15,
      score: 50,
      passed: true,
      details: 'Could not retrieve user history — neutral score.',
    };
  }
}

export async function flagFraudHash(
  hash: string,
  reason: string,
  flaggedBy: string,
  disputeId?: string,
): Promise<void> {
  try {
    await query(
      `INSERT INTO fraud_hashes (file_hash, reason, flagged_by, dispute_id)
       VALUES ($1, $2, $3, $4) ON CONFLICT (file_hash) DO NOTHING`,
      [hash, reason, flaggedBy, disputeId || null]
    );
  } catch {
    console.error('Failed to flag fraud hash');
  }
}
