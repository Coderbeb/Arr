'use client';
import { useState, useEffect, useCallback, Suspense } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import axios from 'axios';
import AppLayout from '@/components/AppLayout';
import { useLanguage } from '@/contexts/LanguageContext';
import { useSocket } from '@/hooks/useSocket';
import { motion, AnimatePresence } from 'framer-motion';
import { getFileUrl } from '@/utils/imageUrl';
import TradeModule from '@/components/TradeModule';

const API = process.env.NEXT_PUBLIC_API_URL || '/api';

function AssistanceDashboardInner() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const { t } = useLanguage();
  const { socket } = useSocket();

  const tab = (searchParams.get('tab') as 'disputes' | 'trades' | 'sell' | 'trade-hub') || 'disputes';
  const [disputes, setDisputes] = useState<any[]>([]);
  const [activeTrades, setActiveTrades] = useState<any[]>([]);
  const [amounts, setAmounts] = useState<any[]>([]);
  const [selectedDispute, setSelectedDispute] = useState<any>(null);
  const [decision, setDecision] = useState('');
  const [notes, setNotes] = useState('');
  const [selectedAmount, setSelectedAmount] = useState('');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [zoomedImage, setZoomedImage] = useState('');

  const token = typeof window !== 'undefined' ? localStorage.getItem('token') : '';
  const headers = { Authorization: `Bearer ${token}` };

  // Sync tab state with searchParams (e.g. from sidebar clicks)
  useEffect(() => {
    setSelectedDispute(null); // Clear selected dispute when changing tabs via sidebar
  }, [searchParams]);

  const fetchData = useCallback(async () => {
    if (!token) { router.push('/'); return; }
    try {
      const [disputeRes, tradesRes, amountsRes] = await Promise.all([
        axios.get(`${API}/assistance/disputes`, { headers }),
        axios.get(`${API}/assistance/trades`, { headers }),
        axios.get(`${API}/trade/amounts`),
      ]);
      setDisputes(disputeRes.data);
      setActiveTrades(tradesRes.data);
      setAmounts(amountsRes.data);
      if (amountsRes.data.length > 0) setSelectedAmount(amountsRes.data[0].id);
    } catch { router.push('/'); }
    finally { setLoading(false); }
  }, []);

  useEffect(() => { fetchData(); }, [fetchData]);

  // Real-time socket updates for assistance manager
  useEffect(() => {
    if (!socket) return;
    const onNewDisputeEvent = () => {
      fetchData(); // Reload dispute list live
    };
    socket.on('dispute:raised', onNewDisputeEvent);
    socket.on('trade:seller_rejected', onNewDisputeEvent);
    socket.on('dispute:scored', onNewDisputeEvent);
    return () => {
      socket.off('dispute:raised', onNewDisputeEvent);
      socket.off('trade:seller_rejected', onNewDisputeEvent);
      socket.off('dispute:scored', onNewDisputeEvent);
    };
  }, [socket, fetchData]);

  async function loadDisputeDetail(id: string) {
    try {
      const res = await axios.get(`${API}/assistance/disputes/${id}`, { headers });
      setSelectedDispute(res.data);
      if (typeof window !== 'undefined') window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch {}
  }

  async function resolveDispute() {
    if (!selectedDispute || !decision || notes.length < 10) return;
    setSaving(true); setError('');
    try {
      await axios.post(`${API}/assistance/disputes/${selectedDispute.id}/resolve`, { decision, notes }, { headers });
      setSuccess(t('Dispute resolved!', 'विवाद सुलझा!'));
      setSelectedDispute(null); setDecision(''); setNotes('');
      fetchData();
    } catch (err: any) { setError(err.response?.data?.error || 'Failed'); }
    finally { setSaving(false); }
  }

  async function postSellOrder() {
    setSaving(true); setError('');
    try {
      await axios.post(`${API}/assistance/sell`, { amount_id: selectedAmount }, { headers });
      setSuccess(t('Sell order posted!', 'विक्रय ऑर्डर पोस्ट किया!'));
      fetchData();
    } catch (err: any) { setError(err.response?.data?.error || 'Failed'); }
    finally { setSaving(false); }
  }

  // ── Score color helper ────────────────────────────────────────
  const scoreColor = (score: number | null) => {
    if (score == null) return 'var(--text-muted)';
    if (score >= 70) return '#22c55e';
    if (score >= 40) return '#f59e0b';
    return '#ef4444';
  };

  const recommendationBadge = (rec: string | null) => {
    if (!rec) return null;
    const map: Record<string, { bg: string; text: string; label: string }> = {
      buyer_likely: { bg: 'rgba(59,130,246,0.15)', text: '#3b82f6', label: t('🛒 Buyer Likely Correct', '🛒 खरीदार सही हो सकता है') },
      seller_likely: { bg: 'rgba(245,158,11,0.15)', text: '#f59e0b', label: t('🏪 Seller Likely Correct', '🏪 विक्रेता सही हो सकता है') },
      inconclusive: { bg: 'rgba(156,163,175,0.15)', text: '#9ca3af', label: t('⚖️ Inconclusive', '⚖️ अनिर्णायक') },
    };
    const m = map[rec] || map.inconclusive;
    return (
      <div style={{
        background: m.bg, color: m.text, padding: '0.5rem 1rem', borderRadius: 8,
        fontWeight: 600, fontSize: '0.85rem', textAlign: 'center', marginBottom: '1rem',
      }}>
        {m.label}
      </div>
    );
  };

  if (loading) return (
    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', minHeight: '100vh' }}>
      <div className="spinner" style={{ width: 48, height: 48 }} />
    </div>
  );

  return (
    <AppLayout title={t('🎧 Assistance Manager', '🎧 सहायता प्रबंधक')} role="assistance">
      <div style={{ paddingBottom: '2rem' }}>
        {error && <div style={{ background: 'rgba(239,68,68,0.1)', border: '1px solid rgba(239,68,68,0.3)', borderRadius: 8, padding: '0.75rem', marginBottom: '1rem', color: '#ef4444' }}>{error}</div>}
        {success && <div style={{ background: 'rgba(34,197,94,0.1)', border: '1px solid rgba(34,197,94,0.3)', borderRadius: 8, padding: '0.75rem', marginBottom: '1rem', color: '#22c55e' }}>{success}</div>}

        {/* Image Zoom Modal */}
        <AnimatePresence>
          {zoomedImage && (
            <motion.div
              initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}
              onClick={() => setZoomedImage('')}
              style={{
                position: 'fixed', inset: 0, zIndex: 9999,
                background: 'rgba(0,0,0,0.85)', display: 'flex',
                alignItems: 'center', justifyContent: 'center', cursor: 'zoom-out',
              }}
            >
              <img src={getFileUrl(zoomedImage)} alt="Zoomed" style={{ maxWidth: '90vw', maxHeight: '90vh', borderRadius: 12 }} />
            </motion.div>
          )}
        </AnimatePresence>

        {/* Top Header Banner for Assistance */}
        {tab === 'disputes' && (
          <div style={{
            background: 'linear-gradient(135deg, rgba(59,130,246,0.15), rgba(147,51,234,0.15))',
            border: '1px solid rgba(59,130,246,0.3)',
            borderRadius: 12, padding: '1rem', marginBottom: '1.25rem',
          }}>
            <div style={{ fontSize: '1.05rem', fontWeight: 700, marginBottom: '0.25rem' }}>
              🎧 {t('Dispute Review Center', 'विवाद समीक्षा केंद्र')}
            </div>
            <div style={{ fontSize: '0.82rem', color: 'var(--text-muted)' }}>
              {t(
                'Select any dispute below and click "Review & Resolve" to inspect proof videos, bank statements, AI accuracy scores, and render a final decision.',
                'प्रमाण वीडियो, बैंक स्टेटमेंट, AI सटीकता स्कोर का निरीक्षण करने और अंतिम निर्णय देने के लिए नीचे किसी भी विवाद पर "समीक्षा और समाधान" पर क्लिक करें।'
              )}
            </div>
          </div>
        )}

        {/* ── DISPUTES LIST ──────────────────────────────────────── */}
        {tab === 'disputes' && !selectedDispute && (
          <div className="card glass">
            <p className="section-title">⚠️ {t('Open Disputes', 'खुले विवाद')}</p>
            {disputes.length === 0 ? (
              <p style={{ textAlign: 'center', padding: '1.5rem', fontSize: '0.9rem' }}>{t('No open disputes ✅', 'कोई खुला विवाद नहीं ✅')}</p>
            ) : disputes.map((d) => (
              <motion.div key={d.id} whileHover={{ scale: 1.01 }}
                className="list-item" style={{ cursor: 'pointer', flexDirection: 'column', alignItems: 'flex-start', gap: '0.5rem' }}
                onClick={() => loadDisputeDetail(d.id)}>
                <div style={{ display: 'flex', justifyContent: 'space-between', width: '100%' }}>
                  <span style={{ fontWeight: 600 }}>₹{parseFloat(d.amount).toLocaleString()}</span>
                  <span className={`badge ${d.status === 'under_review' ? 'badge-warning' : 'badge-info'}`}>{d.status}</span>
                </div>
                <div style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>
                  🛒 {d.buyer_name} ({d.buyer_trades} trades, {d.buyer_strikes} strikes) vs
                  🏪 {d.seller_name} ({d.seller_trades} trades, {d.seller_strikes} strikes)
                </div>
                <div style={{ fontSize: '0.78rem', display: 'flex', gap: '1rem' }}>
                  <span>{t('Buyer AI:', 'खरीदार AI:')} <strong style={{ color: scoreColor(d.buyer_ai_score) }}>{d.buyer_ai_score ?? t('Pending', 'लंबित')}</strong></span>
                  <span>{t('Seller AI:', 'विक्रेता AI:')} <strong style={{ color: scoreColor(d.seller_ai_score) }}>{d.seller_ai_score ?? t('Pending', 'लंबित')}</strong></span>
                </div>
                {d.ai_recommendation && (
                  <div style={{ fontSize: '0.75rem', color: 'var(--gold)' }}>
                    🤖 {d.ai_recommendation === 'buyer_likely' ? t('AI: Buyer likely correct', 'AI: खरीदार सही हो सकता है')
                      : d.ai_recommendation === 'seller_likely' ? t('AI: Seller likely correct', 'AI: विक्रेता सही हो सकता है')
                      : t('AI: Inconclusive', 'AI: अनिर्णायक')}
                  </div>
                )}
                <button
                  className="btn btn-primary btn-sm"
                  style={{ width: '100%', marginTop: '0.5rem', fontWeight: 600 }}
                  onClick={(e) => { e.stopPropagation(); loadDisputeDetail(d.id); }}
                >
                  🔍 {t('Review & Resolve Dispute', 'समीक्षा और समाधान करें')} →
                </button>
              </motion.div>
            ))}
          </div>
        )}

        {/* ── DISPUTE DETAIL ──────────────────────────────────────── */}
        {tab === 'disputes' && selectedDispute && (
          <div className="card glass">
            <button className="btn btn-sm btn-ghost" onClick={() => setSelectedDispute(null)} style={{ marginBottom: '1rem' }}>
              ← {t('Back', 'वापस')}
            </button>

            <h3 style={{ marginBottom: '0.75rem' }}>
              {t('Dispute Detail', 'विवाद विवरण')} — ₹{parseFloat(selectedDispute.amount).toLocaleString()}
            </h3>
            <div style={{ fontSize: '0.85rem', marginBottom: '0.75rem', color: 'var(--text-muted)' }}>
              UTR: <strong style={{ color: 'var(--gold)' }}>{selectedDispute.utr_number}</strong>
            </div>

            {/* AI Recommendation Banner */}
            {recommendationBadge(selectedDispute.ai_recommendation)}

            {selectedDispute.ai_confidence != null && (
              <div style={{ fontSize: '0.8rem', color: 'var(--text-muted)', marginBottom: '1rem', textAlign: 'center' }}>
                {t('AI Confidence:', 'AI विश्वास:')} <strong>{selectedDispute.ai_confidence}%</strong>
              </div>
            )}

            {/* Side-by-Side Proof Comparison */}
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.75rem', marginBottom: '1.25rem' }}>

              {/* ── BUYER SIDE ────────────────────────────────────── */}
              <div className="card" style={{ borderColor: 'rgba(59,130,246,0.3)', padding: '0.75rem' }}>
                <p className="section-title" style={{ color: '#3b82f6', fontSize: '0.85rem' }}>
                  🛒 {t('Buyer', 'खरीदार')}: {selectedDispute.buyer_name}
                </p>
                <div style={{ fontSize: '0.78rem', lineHeight: 1.6, marginBottom: '0.5rem' }}>
                  <div>📱 {selectedDispute.buyer_mobile}</div>
                  <div>{t('Trades:', 'ट्रेड:')} {selectedDispute.buyer_total_trades} · {t('Strikes:', 'स्ट्राइक:')} {selectedDispute.buyer_strikes}</div>
                  <div>{t('Reputation:', 'प्रतिष्ठा:')} {selectedDispute.buyer_reputation}%</div>
                  <div>{t('Past disputes:', 'पिछले विवाद:')} {selectedDispute.buyer_past_disputes}</div>
                </div>

                {/* AI Score */}
                <div style={{ marginTop: '0.5rem', marginBottom: '0.5rem' }}>
                  <div style={{ fontSize: '0.7rem', color: 'var(--text-muted)', marginBottom: '0.25rem' }}>{t('AI Credibility Score', 'AI विश्वसनीयता स्कोर')}</div>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                    <div style={{ flex: 1, height: 8, background: 'rgba(255,255,255,0.1)', borderRadius: 4, overflow: 'hidden' }}>
                      <div style={{
                        width: `${selectedDispute.buyer_ai_score ?? 0}%`, height: '100%', borderRadius: 4,
                        background: scoreColor(selectedDispute.buyer_ai_score),
                        transition: 'width 0.5s ease',
                      }} />
                    </div>
                    <span style={{ fontSize: '0.8rem', fontWeight: 700, color: scoreColor(selectedDispute.buyer_ai_score) }}>
                      {selectedDispute.buyer_ai_score ?? 'N/A'}
                    </span>
                  </div>
                </div>

                {/* Analysis Breakdown */}
                {selectedDispute.buyer_proof_analysis && (
                  <div style={{ marginTop: '0.5rem' }}>
                    <div style={{ fontSize: '0.7rem', color: 'var(--text-muted)', marginBottom: '0.3rem' }}>{t('Analysis Breakdown', 'विश्लेषण विवरण')}</div>
                    {(typeof selectedDispute.buyer_proof_analysis === 'object' && selectedDispute.buyer_proof_analysis.breakdown
                      ? selectedDispute.buyer_proof_analysis.breakdown
                      : []
                    ).map((check: any, i: number) => (
                      <div key={i} style={{
                        display: 'flex', justifyContent: 'space-between', fontSize: '0.72rem',
                        padding: '0.2rem 0', borderBottom: '1px solid rgba(255,255,255,0.05)',
                      }}>
                        <span>{check.passed ? '✅' : '❌'} {check.check}</span>
                        <span style={{ color: scoreColor(check.score) }}>{check.score}</span>
                      </div>
                    ))}
                  </div>
                )}

                {/* Proof Files */}
                <div style={{ marginTop: '0.75rem' }}>
                  <div style={{ fontSize: '0.7rem', color: 'var(--text-muted)', marginBottom: '0.3rem' }}>{t('Evidence', 'साक्ष्य')}</div>
                  {selectedDispute.buyer_upi_screenshot_url && (
                    <div style={{ marginBottom: '0.5rem' }}>
                      <div style={{ fontSize: '0.7rem', marginBottom: '0.2rem' }}>📸 {t('Payment Screenshot', 'भुगतान स्क्रीनशॉट')}</div>
                      <img src={getFileUrl(selectedDispute.buyer_upi_screenshot_url)} alt="Buyer screenshot"
                        onClick={() => setZoomedImage(selectedDispute.buyer_upi_screenshot_url)}
                        style={{ width: '100%', maxHeight: 120, objectFit: 'cover', borderRadius: 6, cursor: 'zoom-in' }} />
                    </div>
                  )}
                  {selectedDispute.buyer_screen_recording_url && (
                    <div style={{ marginBottom: '0.5rem' }}>
                      <div style={{ fontSize: '0.7rem', marginBottom: '0.2rem' }}>🎥 {t('Screen Recording', 'स्क्रीन रिकॉर्डिंग')}</div>
                      <video
                        src={getFileUrl(selectedDispute.buyer_screen_recording_url)}
                        controls
                        playsInline
                        preload="metadata"
                        style={{ width: '100%', maxHeight: 260, borderRadius: 8, background: '#000' }}
                      />
                    </div>
                  )}
                  {selectedDispute.buyer_bank_statement_url && (
                    <div style={{ marginBottom: '0.5rem' }}>
                      <div style={{ fontSize: '0.7rem', marginBottom: '0.2rem' }}>🏦 {t('Bank Statement', 'बैंक स्टेटमेंट')}</div>
                      {selectedDispute.buyer_bank_statement_url.endsWith('.pdf') ? (
                        <a href={getFileUrl(selectedDispute.buyer_bank_statement_url)} target="_blank" rel="noopener noreferrer"
                          style={{ display: 'inline-block', padding: '0.5rem 1rem', background: 'rgba(59,130,246,0.1)', border: '1px solid rgba(59,130,246,0.3)', borderRadius: 8, color: '#3b82f6', fontSize: '0.75rem', textDecoration: 'none' }}>
                          📄 {t('View PDF Statement', 'PDF स्टेटमेंट देखें')}
                        </a>
                      ) : (
                        <img src={getFileUrl(selectedDispute.buyer_bank_statement_url)} alt="Bank statement"
                          onClick={() => setZoomedImage(selectedDispute.buyer_bank_statement_url)}
                          style={{ width: '100%', maxHeight: 120, objectFit: 'cover', borderRadius: 6, cursor: 'zoom-in' }} />
                      )}
                    </div>
                  )}
                  {/* Fraud Flags */}
                  {selectedDispute.buyer_proof_analysis?.flags?.length > 0 && (
                    <div style={{ marginTop: '0.5rem', padding: '0.4rem 0.6rem', background: 'rgba(239,68,68,0.1)', borderRadius: 6 }}>
                      <div style={{ fontSize: '0.65rem', fontWeight: 600, color: '#ef4444', marginBottom: '0.2rem' }}>⚠️ {t('Fraud Flags', 'धोखाधड़ी चेतावनी')}</div>
                      {selectedDispute.buyer_proof_analysis.flags.map((flag: string, i: number) => (
                        <div key={i} style={{ fontSize: '0.65rem', color: '#ef4444' }}>• {flag}</div>
                      ))}
                    </div>
                  )}
                  {selectedDispute.buyer_proof_submitted_at
                    ? <div className="badge badge-success" style={{ marginTop: '0.5rem' }}>{t('Proof uploaded', 'प्रमाण अपलोड')}</div>
                    : <div className="badge badge-danger" style={{ marginTop: '0.5rem' }}>{t('No proof', 'कोई प्रमाण नहीं')}</div>
                  }
                </div>
              </div>

              {/* ── SELLER SIDE ───────────────────────────────────── */}
              <div className="card" style={{ borderColor: 'rgba(245,158,11,0.3)', padding: '0.75rem' }}>
                <p className="section-title" style={{ color: '#f59e0b', fontSize: '0.85rem' }}>
                  🏪 {t('Seller', 'विक्रेता')}: {selectedDispute.seller_name}
                </p>
                <div style={{ fontSize: '0.78rem', lineHeight: 1.6, marginBottom: '0.5rem' }}>
                  <div>📱 {selectedDispute.seller_mobile}</div>
                  <div>{t('Trades:', 'ट्रेड:')} {selectedDispute.seller_total_trades} · {t('Strikes:', 'स्ट्राइक:')} {selectedDispute.seller_strikes}</div>
                  <div>{t('Reputation:', 'प्रतिष्ठा:')} {selectedDispute.seller_reputation}%</div>
                  <div>{t('Past disputes:', 'पिछले विवाद:')} {selectedDispute.seller_past_disputes}</div>
                </div>

                {/* AI Score */}
                <div style={{ marginTop: '0.5rem', marginBottom: '0.5rem' }}>
                  <div style={{ fontSize: '0.7rem', color: 'var(--text-muted)', marginBottom: '0.25rem' }}>{t('AI Credibility Score', 'AI विश्वसनीयता स्कोर')}</div>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                    <div style={{ flex: 1, height: 8, background: 'rgba(255,255,255,0.1)', borderRadius: 4, overflow: 'hidden' }}>
                      <div style={{
                        width: `${selectedDispute.seller_ai_score ?? 0}%`, height: '100%', borderRadius: 4,
                        background: scoreColor(selectedDispute.seller_ai_score),
                        transition: 'width 0.5s ease',
                      }} />
                    </div>
                    <span style={{ fontSize: '0.8rem', fontWeight: 700, color: scoreColor(selectedDispute.seller_ai_score) }}>
                      {selectedDispute.seller_ai_score ?? 'N/A'}
                    </span>
                  </div>
                </div>

                {/* Analysis Breakdown */}
                {selectedDispute.seller_proof_analysis && (
                  <div style={{ marginTop: '0.5rem' }}>
                    <div style={{ fontSize: '0.7rem', color: 'var(--text-muted)', marginBottom: '0.3rem' }}>{t('Analysis Breakdown', 'विश्लेषण विवरण')}</div>
                    {(typeof selectedDispute.seller_proof_analysis === 'object' && selectedDispute.seller_proof_analysis.breakdown
                      ? selectedDispute.seller_proof_analysis.breakdown
                      : []
                    ).map((check: any, i: number) => (
                      <div key={i} style={{
                        display: 'flex', justifyContent: 'space-between', fontSize: '0.72rem',
                        padding: '0.2rem 0', borderBottom: '1px solid rgba(255,255,255,0.05)',
                      }}>
                        <span>{check.passed ? '✅' : '❌'} {check.check}</span>
                        <span style={{ color: scoreColor(check.score) }}>{check.score}</span>
                      </div>
                    ))}
                  </div>
                )}

                {/* Proof Files */}
                <div style={{ marginTop: '0.75rem' }}>
                  <div style={{ fontSize: '0.7rem', color: 'var(--text-muted)', marginBottom: '0.3rem' }}>{t('Evidence', 'साक्ष्य')}</div>
                  {selectedDispute.seller_screen_recording_url && (
                    <div style={{ marginBottom: '0.5rem' }}>
                      <div style={{ fontSize: '0.7rem', marginBottom: '0.2rem' }}>🎥 {t('Screen Recording', 'स्क्रीन रिकॉर्डिंग')}</div>
                      <video
                        src={getFileUrl(selectedDispute.seller_screen_recording_url)}
                        controls
                        playsInline
                        preload="metadata"
                        style={{ width: '100%', maxHeight: 260, borderRadius: 8, background: '#000' }}
                      />
                    </div>
                  )}
                  {selectedDispute.seller_txn_screenshot_url && (
                    <div style={{ marginBottom: '0.5rem' }}>
                      <div style={{ fontSize: '0.7rem', marginBottom: '0.2rem' }}>📸 {t('Transaction Screenshot', 'लेनदेन स्क्रीनशॉट')}</div>
                      <img src={getFileUrl(selectedDispute.seller_txn_screenshot_url)} alt="Seller txn"
                        onClick={() => setZoomedImage(selectedDispute.seller_txn_screenshot_url)}
                        style={{ width: '100%', maxHeight: 120, objectFit: 'cover', borderRadius: 6, cursor: 'zoom-in' }} />
                    </div>
                  )}
                  {/* Fraud Flags */}
                  {selectedDispute.seller_proof_analysis?.flags?.length > 0 && (
                    <div style={{ marginTop: '0.5rem', padding: '0.4rem 0.6rem', background: 'rgba(239,68,68,0.1)', borderRadius: 6 }}>
                      <div style={{ fontSize: '0.65rem', fontWeight: 600, color: '#ef4444', marginBottom: '0.2rem' }}>⚠️ {t('Fraud Flags', 'धोखाधड़ी चेतावनी')}</div>
                      {selectedDispute.seller_proof_analysis.flags.map((flag: string, i: number) => (
                        <div key={i} style={{ fontSize: '0.65rem', color: '#ef4444' }}>• {flag}</div>
                      ))}
                    </div>
                  )}
                  {selectedDispute.seller_proof_submitted_at
                    ? <div className="badge badge-success" style={{ marginTop: '0.5rem' }}>{t('Proof uploaded', 'प्रमाण अपलोड')}</div>
                    : <div className="badge badge-danger" style={{ marginTop: '0.5rem' }}>{t('No proof', 'कोई प्रमाण नहीं')}</div>
                  }
                </div>
              </div>
            </div>

            <hr className="divider" />

            {/* Resolution */}
            <p className="section-title">⚖️ {t('Resolution', 'समाधान')}</p>
            <div style={{ display: 'flex', gap: '0.5rem', marginBottom: '1rem' }}>
              {['buyer', 'seller', 'escalate'].map((d) => (
                <button key={d} className={`btn btn-sm ${decision === d ? 'btn-primary' : 'btn-secondary'}`} onClick={() => setDecision(d)}>
                  {d === 'buyer' ? `✅ ${t('Buyer Wins', 'खरीदार जीता')}` : d === 'seller' ? `✅ ${t('Seller Wins', 'विक्रेता जीता')}` : `🚨 ${t('Escalate', 'आगे भेजें')}`}
                </button>
              ))}
            </div>
            <div className="input-group">
              <label className="input-label">{t('Resolution Notes (min 10 chars)', 'समाधान नोट्स (न्यूनतम 10 अक्षर)')}</label>
              <textarea className="input" style={{ minHeight: 80, resize: 'vertical' }}
                value={notes} onChange={(e) => setNotes(e.target.value)}
                placeholder={t('Explain your reasoning...', 'अपना तर्क समझाएं...')} />
            </div>
            <button className="btn btn-primary btn-full btn-lg" onClick={resolveDispute}
              disabled={saving || !decision || notes.length < 10}>
              {saving ? <span className="spinner" /> : t('Submit Resolution', 'समाधान जमा करें')}
            </button>
          </div>
        )}

        {/* ── ACTIVE TRADES ───────────────────────────────────────── */}
        {tab === 'trades' && (
          <div className="card glass">
            <p className="section-title">📊 {t('Active Trades', 'सक्रिय ट्रेड')} ({activeTrades.length})</p>
            {activeTrades.length === 0 ? (
              <p style={{ textAlign: 'center', padding: '1rem' }}>{t('No active trades', 'कोई सक्रिय ट्रेड नहीं')}</p>
            ) : activeTrades.map((tr) => (
              <div key={tr.id} className="list-item" style={{ flexDirection: 'column', alignItems: 'flex-start', gap: '0.3rem' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', width: '100%' }}>
                  <span style={{ fontWeight: 600 }}>₹{parseFloat(tr.amount).toLocaleString()}</span>
                  <span className={`badge ${tr.status === 'pending_payment' ? 'badge-info' : tr.status === 'payment_submitted' ? 'badge-gold' : 'badge-warning'}`}>{tr.status}</span>
                </div>
                <div style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>
                  {tr.buyer_name} ↔ {tr.seller_name}
                </div>
              </div>
            ))}
          </div>
        )}

        {/* ── TRADE HUB ──────────────────────────────────────────── */}
        {tab === 'trade-hub' && (
          <div className="card glass">
            <TradeModule />
          </div>
        )}

        {/* ── SEED COINS ─────────────────────────────────────────── */}
        {tab === 'sell' && (
          <div className="card glass">
            <p className="section-title">📤 {t('Seed Coins (Post Sell Order)', 'कॉइन सीड (विक्रय ऑर्डर पोस्ट)')}</p>
            <p style={{ fontSize: '0.85rem', marginBottom: '1rem', color: 'var(--text-muted)' }}>
              {t('Post sell orders from your assistance account to seed the market with coins.', 'बाजार में कॉइन डालने के लिए अपने सहायता खाते से विक्रय ऑर्डर पोस्ट करें।')}
            </p>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: '0.5rem', marginBottom: '1rem' }}>
              {amounts.map((a) => (
                <button key={a.id}
                  className={`btn btn-sm ${selectedAmount === a.id ? 'btn-primary' : 'btn-secondary'}`}
                  onClick={() => setSelectedAmount(a.id)}>
                  ₹{parseFloat(a.amount).toLocaleString()}
                </button>
              ))}
            </div>
            <button className="btn btn-primary btn-full btn-lg" onClick={postSellOrder} disabled={saving || !selectedAmount}>
              {saving ? <span className="spinner" /> : t('Post Sell Order', 'विक्रय ऑर्डर पोस्ट करें')}
            </button>
          </div>
        )}
      </div>
    </AppLayout>
  );
}

export default function AssistanceDashboard() {
  return (
    <Suspense fallback={
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', minHeight: '100vh' }}>
        <div className="spinner" style={{ width: 48, height: 48, borderColor: 'var(--gold) transparent var(--gold) transparent' }} />
      </div>
    }>
      <AssistanceDashboardInner />
    </Suspense>
  );
}
