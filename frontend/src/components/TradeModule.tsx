'use client';
import { useState, useEffect, useCallback, useRef, Suspense } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import axios from 'axios';
import { useSocket } from '@/hooks/useSocket';
import { useLanguage } from '@/contexts/LanguageContext';
import { motion, AnimatePresence } from 'framer-motion';
import { getFileUrl } from '@/utils/imageUrl';

const API = process.env.NEXT_PUBLIC_API_URL || '/api';

// Web Audio API beep for notifications
function playNotificationSound() {
  try {
    const AudioContext = window.AudioContext || (window as any).webkitAudioContext;
    if (!AudioContext) return;
    const ctx = new AudioContext();
    const osc = ctx.createOscillator();
    const gainNode = ctx.createGain();
    osc.type = 'sine';
    osc.frequency.setValueAtTime(880, ctx.currentTime); // A5 note
    gainNode.gain.setValueAtTime(0.1, ctx.currentTime);
    gainNode.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3);
    osc.connect(gainNode);
    gainNode.connect(ctx.destination);
    osc.start();
    osc.stop(ctx.currentTime + 0.3);
  } catch (e) {
    console.warn('Audio play failed', e);
  }
}

function TradeModuleInner() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const initialMode = searchParams.get('mode') || 'buy';

  const [mode, setMode] = useState<'buy' | 'sell'>(initialMode as any);
  const [amounts, setAmounts] = useState<any[]>([]);
  const [selectedAmount, setSelectedAmount] = useState<string>('');
  const [loading, setLoading] = useState(false);
  const [queueInfo, setQueueInfo] = useState<any>(null);
  const [inQueue, setInQueue] = useState(false);
  const [activeTrade, setActiveTrade] = useState<any>(null);
  const [openOrder, setOpenOrder] = useState<any>(null);
  const [deepLinks, setDeepLinks] = useState<any>(null);
  const [utrNumber, setUtrNumber] = useState('');
  const [selectedUpiApp, setSelectedUpiApp] = useState('');
  const [history, setHistory] = useState<any[]>([]);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [countdown, setCountdown] = useState<string>('');
  const countdownRef = useRef<NodeJS.Timeout | null>(null);

  // Proof upload states
  const [screenshotFile, setScreenshotFile] = useState<File | null>(null);
  const [screenshotPreview, setScreenshotPreview] = useState('');
  const [rejectRecording, setRejectRecording] = useState<File | null>(null);
  const [rejectBankStatement, setRejectBankStatement] = useState<File | null>(null);
  const [showRejectModal, setShowRejectModal] = useState(false);

  // User Profile & Sell UPI Receiving states
  const [userProfile, setUserProfile] = useState<any>(null);
  const [sellUpiId, setSellUpiId] = useState('');
  const [sellUpiApp, setSellUpiApp] = useState('phonepe');
  const [selectedHistoryTrade, setSelectedHistoryTrade] = useState<any>(null);
  const [copiedUtr, setCopiedUtr] = useState(false);

  // New UI Overhaul states
  const [buyerStep, setBuyerStep] = useState(1);
  const [copiedAmount, setCopiedAmount] = useState(false);
  const [copiedUpi, setCopiedUpi] = useState(false);
  const [timerProgress, setTimerProgress] = useState(100);
  const prevTradeStatus = useRef<string | null>(null);

  // Appeal flow states (buyer side after seller_rejected)
  const [disputeInfo, setDisputeInfo] = useState<any>(null);
  const [appealRecording, setAppealRecording] = useState<File | null>(null);
  const [appealBankStatement, setAppealBankStatement] = useState<File | null>(null);
  const [showAppealModal, setShowAppealModal] = useState(false);

  const { lang, t } = useLanguage();
  const token = typeof window !== 'undefined' ? localStorage.getItem('token') : '';
  const headers = { Authorization: `Bearer ${token}` };

  // ── Socket.IO connection ──────────────────────────────────────
  const { socket, connected } = useSocket();

  // ── Fetch preset amounts ──────────────────────────────────────
  const fetchAmounts = useCallback(async () => {
    try {
      const res = await axios.get(`${API}/trade/amounts`);
      setAmounts(res.data);
      if (res.data.length > 0 && !selectedAmount) setSelectedAmount(res.data[0].id);
    } catch {}
  }, []);

  // ── Fetch trade history ───────────────────────────────────────
  const fetchHistory = useCallback(async () => {
    try {
      const res = await axios.get(`${API}/trade/history?_t=${Date.now()}`, { headers });
      setHistory(res.data.slice(0, 20));
    } catch {}
  }, [headers]);

  const fetchActiveTrade = useCallback(async () => {
    try {
      const res = await axios.get(`${API}/trade/my-active?_t=${Date.now()}`, { headers });
      if (res.data.trade) {
        const trade = res.data.trade;
        setActiveTrade(trade);
        setDeepLinks(res.data.deepLinks);
        
        // Play sound if status changed (e.g. matched, or payment submitted)
        if (prevTradeStatus.current !== null && prevTradeStatus.current !== trade.status) {
          playNotificationSound();
        }
        prevTradeStatus.current = trade.status;

        // If user is seller and trade is pending_payment, switch to sell mode
        if (trade.my_role === 'seller') setMode('sell');
        else setMode('buy');
      } else {
        setActiveTrade(null);
        prevTradeStatus.current = null;
      }
      
      if (res.data.openOrder) {
        setOpenOrder(res.data.openOrder);
        setMode('sell');
      } else {
        setOpenOrder(null);
      }
      
      // Load dispute info if available
      if (res.data.dispute) {
        setDisputeInfo(res.data.dispute);
      }
    } catch {}
  }, [headers]);

  // ── Sockets ───────────────────────────────────────────────────
  useEffect(() => {
    if (!connected || !socket) return;
    
    socket.on('trade:update', () => {
      fetchActiveTrade();
      fetchHistory();
    });

    socket.on('dispute:resolved', () => {
      fetchActiveTrade();
      fetchHistory();
    });

    socket.on('dispute:escalated', () => {
      fetchActiveTrade();
    });
    
    return () => {
      socket.off('trade:update');
      socket.off('dispute:resolved');
      socket.off('dispute:escalated');
    };
  }, [connected, socket, fetchActiveTrade, fetchHistory]);

  const fetchUserProfile = useCallback(async () => {
    try {
      const res = await axios.get(`${API}/auth/me?_t=${Date.now()}`, { headers });
      setUserProfile(res.data);
      if (res.data.upi_id && !sellUpiId) setSellUpiId(res.data.upi_id);
      if (res.data.upi_app && !sellUpiApp) setSellUpiApp(res.data.upi_app);
    } catch {}
  }, [headers, sellUpiId, sellUpiApp]);

  useEffect(() => {
    fetchAmounts();
    fetchHistory();
    fetchActiveTrade();
    fetchUserProfile();
  }, [fetchAmounts, fetchHistory, fetchActiveTrade, fetchUserProfile]);

  // ── Countdown Timer ───────────────────────────────────────────
  useEffect(() => {
    if (countdownRef.current) clearInterval(countdownRef.current);

    if (!activeTrade) {
      setCountdown('');
      setTimerProgress(100);
      return;
    }

    const d = activeTrade.status === 'pending_payment'
      ? new Date(activeTrade.payment_deadline)
      : (activeTrade.status === 'seller_rejected' && disputeInfo?.proof_deadline)
        ? new Date(disputeInfo.proof_deadline)
        : null;

    if (!d) {
      setCountdown('');
      setTimerProgress(100);
      return;
    }

    function tick() {
      const diff = d!.getTime() - Date.now();
      if (diff <= 0) {
        setCountdown('00:00');
        setTimerProgress(0);
        if (countdownRef.current) clearInterval(countdownRef.current);
        // Trade expired — refresh state
        setActiveTrade(null);
        fetchActiveTrade();
        fetchHistory();
        return;
      }
      const mins = Math.floor(diff / 60000);
      const secs = Math.floor((diff % 60000) / 1000);
      setCountdown(`${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`);

      // Update progress bar
      const totalMs = activeTrade.status === 'seller_rejected' ? 15 * 60 * 1000 : 30 * 60 * 1000;
      setTimerProgress(Math.max(0, Math.min(100, (diff / totalMs) * 100)));
    }

    tick();
    countdownRef.current = setInterval(tick, 1000);

    return () => {
      if (countdownRef.current) clearInterval(countdownRef.current);
    };
  }, [activeTrade?.id, activeTrade?.status]);

  // ── Socket.IO Event Listeners ─────────────────────────────────
  useEffect(() => {
    if (!socket) return;

    // Buyer matched to a seller's order
    const onTradeMatched = (data: any) => {
      setQueueInfo(null);
      setInQueue(false);
      setActiveTrade({
        id: data.trade_id,
        order_id: data.order_id,
        amount: data.amount,
        seller_upi_id: data.seller_upi_id,
        seller_upi_app: data.seller_upi_app,
        status: 'pending_payment',
        payment_deadline: data.payment_deadline,
        my_role: data.message ? 'seller' : 'buyer', // Seller gets a message field
      });
      if (data.deep_links) setDeepLinks(data.deep_links);
      setSuccess(t(
        'Trade matched! Complete payment now.',
        'ट्रेड मिला! अभी भुगतान करें।'
      ));
      setError('');
    };

    // Buyer submitted payment — seller sees this
    const onPaymentSubmitted = (data: any) => {
      setActiveTrade((prev: any) => prev ? {
        ...prev,
        status: 'payment_submitted',
        utr_number: data.utr_number,
      } : prev);
      setSuccess(t(
        'Buyer has submitted payment. Please verify in your UPI app.',
        'खरीदार ने भुगतान जमा किया है। अपने UPI ऐप में सत्यापित करें।'
      ));
    };

    // Trade completed
    const onTradeCompleted = (data: any) => {
      setActiveTrade(null);
      setOpenOrder(null);
      setDeepLinks(null);
      setSuccess(t(
        `Trade completed! ₹${data.amount_received} credited to your wallet.`,
        `ट्रेड पूर्ण! ₹${data.amount_received} आपके वॉलेट में जमा।`
      ));
      fetchHistory();
    };

    // Trade cancelled (timeout)
    const onTradeCancelled = (data: any) => {
      setActiveTrade(null);
      setDeepLinks(null);
      setError(data.reason || t('Trade was cancelled.', 'ट्रेड रद्द कर दिया गया।'));
      fetchHistory();
    };

    // Dispute raised (legacy — kept for backwards compatibility)
    const onDisputeRaised = (data: any) => {
      fetchActiveTrade(); // Re-fetch to get the seller_rejected state + dispute info
      setError(t(
        `Dispute raised. Upload proof within ${data.deadline_minutes} minutes.`,
        `विवाद उठाया गया। ${data.deadline_minutes} मिनट में प्रमाण अपलोड करें।`
      ));
      fetchHistory();
    };

    // Seller rejected the payment — buyer sees appeal UI
    const onSellerRejected = (data: any) => {
      setDisputeInfo({ id: data.dispute_id });
      fetchActiveTrade(); // Re-fetch to get updated trade status
      setError(t(
        `Seller rejected your payment. Appeal within ${data.deadline_minutes} minutes with proof.`,
        `विक्रेता ने आपका भुगतान अस्वीकार किया। ${data.deadline_minutes} मिनट में प्रमाण के साथ अपील करें।`
      ));
    };

    // Order matched (for seller: their order got picked up)
    const onOrderMatched = (data: any) => {
      setOpenOrder(null);
      setActiveTrade({
        id: data.trade_id,
        order_id: data.order_id,
        amount: data.amount,
        status: 'pending_payment',
        my_role: 'seller',
      });
      setSuccess(t(
        'Buyer matched! Waiting for their payment.',
        'खरीदार मिला! उनके भुगतान की प्रतीक्षा।'
      ));
    };

    socket.on('trade:matched', onTradeMatched);
    socket.on('trade:payment_submitted', onPaymentSubmitted);
    socket.on('trade:completed', onTradeCompleted);
    socket.on('trade:cancelled', onTradeCancelled);
    socket.on('dispute:raised', onDisputeRaised);
    socket.on('trade:seller_rejected', onSellerRejected);
    socket.on('order:matched', onOrderMatched);

    return () => {
      socket.off('trade:matched', onTradeMatched);
      socket.off('trade:payment_submitted', onPaymentSubmitted);
      socket.off('trade:completed', onTradeCompleted);
      socket.off('trade:cancelled', onTradeCancelled);
      socket.off('dispute:raised', onDisputeRaised);
      socket.off('trade:seller_rejected', onSellerRejected);
      socket.off('order:matched', onOrderMatched);
    };
  }, [socket]);

  // ── SELL: Post sell order ─────────────────────────────────────
  async function handleSell() {
    if (!sellUpiId || !sellUpiId.includes('@')) {
      setError(t('Please enter a valid UPI ID (e.g. username@upi) to receive payment.', 'भुगतान प्राप्त करने के लिए कृपया एक वैध UPI आईडी (उदा. username@upi) दर्ज करें।'));
      return;
    }
    setLoading(true); setError(''); setSuccess('');
    try {
      const res = await axios.post(`${API}/trade/sell`, {
        amount_id: selectedAmount,
        upi_id: sellUpiId,
        upi_app: sellUpiApp
      }, { headers });
      setOpenOrder(res.data.order);
      setSuccess(t('Sell order posted! Waiting for buyer match.', 'विक्रय ऑर्डर पोस्ट किया! खरीदार मिलान की प्रतीक्षा।'));
      fetchHistory();
      fetchUserProfile();
    } catch (err: any) {
      setError(err.response?.data?.error || 'Failed to post sell order');
    } finally { setLoading(false); }
  }

  // ── SELL: Cancel sell order ───────────────────────────────────
  async function handleCancelSellOrder() {
    if (!openOrder && !activeTrade) return;
    
    // Determine the order_id depending on if we are matched or not
    const order_id = openOrder ? openOrder.id : (activeTrade?.order_id || activeTrade?.id); // The backend needs order_id. activeTrade has order_id if fetched properly. 
    // Wait, let's just make sure we have the order ID. The backend expects order_id. 
    // Actually in `fetchActiveTrade`, activeTrade might not have order_id. Let's send a request and let the backend figure it out, or we can use openOrder.id.
    // If activeTrade is active, we might need order_id. Let's fetch it from activeTrade if it's there.
    
    if (!window.confirm(t('Are you sure you want to cancel this sell order?', 'क्या आप वाकई इस विक्रय ऑर्डर को रद्द करना चाहते हैं?'))) return;
    setLoading(true); setError('');
    try {
      const targetId = openOrder ? openOrder.id : activeTrade.order_id;
      const res = await axios.post(`${API}/trade/sell/cancel/${targetId}`, {}, { headers });
      setSuccess(res.data.message || t('Order cancelled.', 'ऑर्डर रद्द।'));
      if (openOrder) {
        setOpenOrder(null);
      }
      fetchHistory();
      fetchUserProfile();
    } catch (err: any) {
      setError(err.response?.data?.error || 'Failed to cancel order');
    } finally { setLoading(false); }
  }

  // ── BUY: Join buyer queue ─────────────────────────────────────
  async function handleJoinQueue() {
    setLoading(true); setError(''); setSuccess('');
    try {
      const res = await axios.post(`${API}/trade/buy/queue`, { amount_id: selectedAmount }, { headers });
      setQueueInfo(res.data);
      setInQueue(true);
      setSuccess(t(`You are #${res.data.position} in queue!`, `आप कतार में #${res.data.position} हैं!`));
    } catch (err: any) {
      setError(err.response?.data?.error || 'Failed to join queue');
    } finally { setLoading(false); }
  }

  // ── BUY: Submit payment (UTR + screenshot) ────────────────────
  async function handlePaymentSubmit() {
    if (!activeTrade) return;
    if (!/^[A-Za-z0-9]{12,22}$/.test(utrNumber)) {
      setError(t('Invalid UTR. Must be 12-22 alphanumeric characters.', 'अमान्य UTR। 12-22 अल्फ़ान्यूमेरिक अक्षर होने चाहिए।')); return;
    }
    if (!screenshotFile) {
      setError(t('Please upload a payment screenshot.', 'कृपया भुगतान का स्क्रीनशॉट अपलोड करें।')); return;
    }
    setLoading(true); setError('');
    try {
      const formData = new FormData();
      formData.append('utr_number', utrNumber);
      formData.append('buyer_upi_app', selectedUpiApp);
      formData.append('screenshot', screenshotFile);
      await axios.post(`${API}/trade/pay/${activeTrade.id}`, formData, {
        headers: { ...headers, 'Content-Type': 'multipart/form-data' },
      });
      setSuccess(t('Payment submitted with proof! Waiting for seller confirmation.', 'प्रमाण के साथ भुगतान जमा! विक्रेता की पुष्टि की प्रतीक्षा।'));
      setActiveTrade({ ...activeTrade, status: 'payment_submitted' });
      setScreenshotFile(null); setScreenshotPreview('');
    } catch (err: any) {
      setError(err.response?.data?.error || 'Payment submission failed');
    } finally { setLoading(false); }
  }

  // ── SELL: Confirm payment received ────────────────────────────
  async function handleConfirm() {
    if (!activeTrade) return;
    setLoading(true);
    try {
      await axios.post(`${API}/trade/confirm/${activeTrade.id}`, {}, { headers });
      setSuccess(t('Trade confirmed! Coins released.', 'ट्रेड पुष्ट! कॉइन जारी।'));
      setActiveTrade(null); setOpenOrder(null); setDeepLinks(null);
      fetchHistory();
    } catch (err: any) { setError(err.response?.data?.error || 'Failed'); }
    finally { setLoading(false); }
  }

  // ── BUY: Cancel Trade ──────────────────────────────────────────
  async function handleCancelTrade() {
    if (!activeTrade) return;
    if (!window.confirm(t('Are you sure you want to cancel this trade?', 'क्या आप वाकई इस ट्रेड को रद्द करना चाहते हैं?'))) return;
    setLoading(true); setError('');
    try {
      await axios.post(`${API}/trade/cancel/${activeTrade.id}`, {}, { headers });
      setSuccess(t('Trade cancelled. You can join the queue again.', 'ट्रेड रद्द कर दिया गया। आप फिर से कतार में शामिल हो सकते हैं।'));
      setActiveTrade(null);
      setDeepLinks(null);
      fetchHistory();
    } catch (err: any) {
      setError(err.response?.data?.error || 'Failed to cancel trade');
    } finally { setLoading(false); }
  }


  // ── SELL: Reject payment → upload recording + bank statement → dispute ─────────
  async function handleReject() {
    if (!activeTrade || !rejectRecording) {
      setError(t('Please upload a screen recording of your UPI app before rejecting.', 'अस्वीकार करने से पहले अपने UPI ऐप की स्क्रीन रिकॉर्डिंग अपलोड करें।'));
      return;
    }
    setLoading(true);
    try {
      const formData = new FormData();
      formData.append('screen_recording', rejectRecording);
      if (rejectBankStatement) formData.append('bank_statement', rejectBankStatement);
      const res = await axios.post(`${API}/trade/reject/${activeTrade.id}`, formData, {
        headers: { ...headers, 'Content-Type': 'multipart/form-data' },
      });
      setSuccess(t('Payment rejected with proof. Buyer has been notified to appeal.', 'प्रमाण के साथ भुगतान अस्वीकार। खरीदार को अपील के लिए सूचित किया गया।'));
      setShowRejectModal(false); setRejectRecording(null); setRejectBankStatement(null);
      
      // Optimistic update
      setActiveTrade((prev: any) => prev ? { ...prev, status: 'seller_rejected' } : prev);
      
      // Re-fetch to get the updated state
      fetchActiveTrade();
      fetchHistory();
    } catch (err: any) { setError(err.response?.data?.error || 'Failed'); }
    finally { setLoading(false); }
  }

  // ── BUY: Appeal seller rejection → upload proof ───────────────
  async function handleAppeal() {
    if (!activeTrade || (!appealRecording && !appealBankStatement)) {
      setError(t('Please upload at least one proof file (screen recording or bank statement).', 'कृपया कम से कम एक प्रमाण फ़ाइल अपलोड करें (स्क्रीन रिकॉर्डिंग या बैंक स्टेटमेंट)।'));
      return;
    }
    setLoading(true); setError('');
    try {
      const formData = new FormData();
      if (appealRecording) formData.append('screen_recording', appealRecording);
      if (appealBankStatement) formData.append('bank_statement', appealBankStatement);
      await axios.post(`${API}/dispute/appeal/${activeTrade.id}`, formData, {
        headers: { ...headers, 'Content-Type': 'multipart/form-data' },
      });
      setSuccess(t('Appeal submitted! AI is analyzing your proof. An assistant will review shortly.', 'अपील जमा! AI आपके प्रमाण का विश्लेषण कर रहा है। एक सहायक जल्द ही समीक्षा करेगा।'));
      setShowAppealModal(false); setAppealRecording(null); setAppealBankStatement(null);
      
      // Optimistic update
      setActiveTrade((prev: any) => prev ? { ...prev, status: 'disputed' } : prev);
      
      fetchActiveTrade();
      fetchHistory();
    } catch (err: any) { setError(err.response?.data?.error || 'Failed to submit appeal'); }
    finally { setLoading(false); }
  }

  // ── UPI deep link opener ──────────────────────────────────────
  function openUpiApp(app: string) {
    setSelectedUpiApp(app);
    if (!deepLinks) return;

    if (typeof window === 'undefined') return;

    // Always try the universal upi://pay link first — it triggers the OS UPI app chooser
    // which is the most reliable method on both Android and iOS
    const universalLink = deepLinks.universal;
    const specificLink = deepLinks[app];

    if (universalLink) {
      // Try universal link (opens OS app chooser)
      window.location.href = universalLink;
    }
  }

  // ── Status badge renderer ─────────────────────────────────────
  const statusBadge = (status: string) => {
    const map: Record<string, { class: string; label: string }> = {
      completed: { class: 'badge-success', label: t('Completed', 'पूर्ण') },
      cancelled: { class: 'badge-danger', label: t('Cancelled', 'रद्द') },
      disputed: { class: 'badge-warning', label: t('Disputed', 'विवादित') },
      seller_rejected: { class: 'badge-danger', label: t('Rejected — Appeal', 'अस्वीकार — अपील') },
      pending_payment: { class: 'badge-info', label: t('Pending', 'लंबित') },
      payment_submitted: { class: 'badge-gold', label: t('Awaiting Confirm', 'पुष्टि प्रतीक्षा') },
      refunded: { class: 'badge-danger', label: t('Refunded', 'वापसी') },
    };
    const s = map[status] || { class: 'badge-info', label: status };
    return <span className={`badge ${s.class}`}>{s.label}</span>;
  };

  // ── Check if action buttons should be disabled ────────────────
  const hasActiveEngagement = !!activeTrade || !!openOrder || inQueue;

  return (
    <div className="grid">
      {/* Left Column: Trade Actions & Status */}
      {!connected && (
        <div style={{ background: 'rgba(251,191,36,0.1)', border: '1px solid rgba(251,191,36,0.3)', borderRadius: 8, padding: '0.5rem 0.75rem', marginBottom: '1rem', color: '#fbbf24', fontSize: '0.8rem', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
          <span className="spinner" style={{ width: 14, height: 14 }} /> {t('Connecting to live updates...', 'लाइव अपडेट से कनेक्ट हो रहा है...')}
        </div>
      )}

      {error && <div style={{ background: 'rgba(239,68,68,0.1)', border: '1px solid rgba(239,68,68,0.3)', borderRadius: 8, padding: '0.75rem', marginBottom: '1rem', color: '#ef4444', fontSize: '0.9rem' }}>{error}</div>}
      {success && <div style={{ background: 'rgba(34,197,94,0.1)', border: '1px solid rgba(34,197,94,0.3)', borderRadius: 8, padding: '0.75rem', marginBottom: '1rem', color: '#22c55e', fontSize: '0.9rem' }}>{success}</div>}

      <div className="trade-grid" style={{ display: 'grid', gap: '1.25rem', gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))' }}>
        
        {/* Left Column: Actions */}
        <div>

        {/* Mode Selector */}
        <div style={{ display: 'flex', gap: '0.5rem', marginBottom: '1.25rem' }}>
          <button className={`btn ${mode === 'buy' ? 'btn-primary' : 'btn-ghost'}`} style={{ flex: 1 }} onClick={() => { setMode('buy'); setError(''); setSuccess(''); }}>
            📥 {t('Buy', 'खरीदें')}
          </button>
          <button className={`btn ${mode === 'sell' ? 'btn-primary' : 'btn-ghost'}`} style={{ flex: 1 }} onClick={() => { setMode('sell'); setError(''); setSuccess(''); }}>
            📤 {t('Sell', 'बेचें')}
          </button>
        </div>

        {/* Amount Selection */}
        {!hasActiveEngagement && (
          <div className="card glass" style={{ marginBottom: '1.25rem' }}>
            <p className="section-title">{t('Select Amount', 'राशि चुनें')}</p>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: '0.5rem', marginBottom: '1rem' }}>
              {amounts.map((a) => (
                <button key={a.id}
                  className={`btn btn-sm ${selectedAmount === a.id ? 'btn-primary' : 'btn-secondary'}`}
                  onClick={() => setSelectedAmount(a.id)}>
                  ₹{parseFloat(a.amount).toLocaleString()}
                </button>
              ))}
            </div>

            {/* Inline Sell Receiving UPI Selector */}
            {mode === 'sell' && (
              <div style={{ background: 'rgba(255,255,255,0.03)', border: '1px solid rgba(255,255,255,0.1)', borderRadius: 12, padding: '1rem', marginBottom: '1rem' }}>
                <p style={{ fontSize: '0.85rem', fontWeight: 600, color: 'var(--gold)', marginBottom: '0.75rem' }}>
                  💳 {t('Select Receiving UPI App & Details', 'प्राप्तकर्ता UPI ऐप और विवरण चुनें')}
                </p>
                <label className="input-label" style={{ fontSize: '0.78rem' }}>
                  {t('Select UPI App:', 'UPI ऐप चुनें:')}
                </label>
                <div className="upi-grid" style={{ marginBottom: '0.75rem' }}>
                  {[
                    { id: 'phonepe', name: 'PhonePe', icon: '📱' },
                    { id: 'gpay', name: 'Google Pay', icon: '💳' },
                    { id: 'paytm', name: 'Paytm', icon: '💰' },
                  ].map((app) => (
                    <button
                      key={app.id}
                      type="button"
                      className={`upi-btn ${sellUpiApp === app.id ? 'selected' : ''}`}
                      onClick={() => setSellUpiApp(app.id)}
                    >
                      <span style={{ fontSize: '1.3rem' }}>{app.icon}</span>
                      {app.name}
                    </button>
                  ))}
                </div>

                <div className="input-group" style={{ marginBottom: 0 }}>
                  <label className="input-label" style={{ fontSize: '0.78rem' }}>
                    {t('Your Receiving UPI ID', 'आपकी प्राप्तकर्ता UPI आईडी')}
                  </label>
                  <input
                    className="input"
                    value={sellUpiId}
                    onChange={(e) => setSellUpiId(e.target.value.trim())}
                    placeholder="e.g. mobile@ybl or username@paytm"
                  />
                  {!userProfile?.upi_id && (
                    <p style={{ fontSize: '0.72rem', color: 'var(--text-muted)', marginTop: '0.3rem' }}>
                      💡 {t('This will be saved to your settings for receiving sell payouts.', 'यह आपके विक्रय भुगतान प्राप्त करने के लिए आपकी सेटिंग्स में सहेजा जाएगा।')}
                    </p>
                  )}
                </div>
              </div>
            )}

            <div style={{ marginTop: '1rem' }}>
              {mode === 'sell' ? (
                <button className="btn btn-primary btn-full btn-lg" onClick={handleSell} disabled={loading || !selectedAmount || !sellUpiId}>
                  {loading ? <span className="spinner" /> : t('Start Sell', 'सेल शुरू करें')}
                </button>
              ) : (
                <button className="btn btn-primary btn-full btn-lg" onClick={handleJoinQueue} disabled={loading || !selectedAmount}>
                  {loading ? <span className="spinner" /> : t('Join Buy Queue', 'खरीद कतार में शामिल हों')}
                </button>
              )}
            </div>
          </div>
        )}

        {/* Queue Waiting State (Buyer) */}
        {inQueue && !activeTrade && (
          <div className="card card-glow glass" style={{ marginBottom: '1.25rem', textAlign: 'center' }}>
            <p className="section-title">🕐 {t('In Queue', 'कतार में')}</p>
            <div style={{ fontSize: '2rem', fontWeight: 700, color: 'var(--gold)', marginBottom: '0.5rem' }}>
              #{queueInfo?.position || '—'}
            </div>
            <p style={{ fontSize: '0.85rem', color: 'var(--text-muted)', marginBottom: '1rem' }}>
              {t('Waiting for a seller to match...', 'विक्रेता के मिलान की प्रतीक्षा...')}
            </p>
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '0.5rem', color: 'var(--gold)', fontSize: '0.8rem' }}>
              <span className="spinner" style={{ width: 14, height: 14 }} />
              {t('You will be matched automatically', 'आप स्वचालित रूप से मिलाए जाएंगे')}
            </div>
          </div>
        )}

        {/* Open Sell Order Waiting State (Seller) */}
        {openOrder && !activeTrade && (
          <div className="card card-glow glass" style={{ marginBottom: '1.25rem', textAlign: 'center' }}>
            <p className="section-title">📤 {t('Sell Order Active', 'विक्रय ऑर्डर सक्रिय')}</p>
            <div style={{ fontSize: '1.5rem', fontWeight: 700, color: 'var(--gold)', marginBottom: '0.5rem' }}>
              ₹{parseFloat(openOrder.amount).toLocaleString()}
            </div>
            <p style={{ fontSize: '0.85rem', color: 'var(--text-muted)', marginBottom: '1rem' }}>
              {t('Waiting for a buyer to be matched...', 'खरीदार के मिलान की प्रतीक्षा...')}
            </p>
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '0.5rem', color: 'var(--gold)', fontSize: '0.8rem' }}>
              <span className="spinner" style={{ width: 14, height: 14 }} />
              {t('Coins locked in escrow', 'कॉइन एस्क्रो में लॉक हैं')}
            </div>
            <button className="btn btn-danger btn-full" onClick={handleCancelSellOrder} style={{ marginTop: '1rem', opacity: 0.8 }} disabled={loading}>
              {loading ? <span className="spinner" /> : t('Cancel Order', 'ऑर्डर रद्द करें')}
            </button>
          </div>
        )}

        {/* ────────────────────────────────────────────────────── */}
        {/* ACTIVE TRADE PANEL                                    */}
        {/* ────────────────────────────────────────────────────── */}
        {activeTrade && (
          <div className="card card-glow glass" style={{ marginBottom: '1.25rem' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.75rem' }}>
              <p className="section-title" style={{ margin: 0 }}>🔥 {t('Active Trade', 'सक्रिय ट्रेड')}</p>
            </div>

            {/* Dynamic Timer Bar */}
            {countdown && (
              <div style={{ marginBottom: '1.25rem' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '0.4rem', fontSize: '0.8rem', fontWeight: 600 }}>
                  <span style={{ color: 'var(--text-muted)' }}>{t('Time Remaining', 'शेष समय')}</span>
                  <span style={{ 
                    color: timerProgress < 10 ? '#ef4444' : timerProgress < 50 ? '#f59e0b' : '#10b981',
                    fontVariantNumeric: 'tabular-nums' 
                  }}>
                    ⏱ {countdown}
                  </span>
                </div>
                <div style={{ 
                  height: 8, 
                  background: 'rgba(255,255,255,0.05)', 
                  borderRadius: 4, 
                  overflow: 'hidden' 
                }}>
                  <motion.div
                    initial={{ width: `${timerProgress}%` }}
                    animate={{ width: `${timerProgress}%` }}
                    transition={{ duration: 1, ease: 'linear' }}
                    style={{
                      height: '100%',
                      background: timerProgress < 10 ? '#ef4444' : timerProgress < 50 ? '#f59e0b' : '#10b981',
                      boxShadow: `0 0 10px ${timerProgress < 10 ? '#ef4444' : timerProgress < 50 ? '#f59e0b' : '#10b981'}80`,
                    }}
                  />
                </div>
              </div>
            )}

            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '1rem', alignItems: 'center' }}>
              <div>
                <div style={{ fontSize: '1.8rem', fontWeight: 700, display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                  ₹{parseFloat(activeTrade.amount).toLocaleString()}
                  {activeTrade.my_role === 'buyer' && activeTrade.status === 'pending_payment' && (
                    <button 
                      onClick={() => {
                        navigator.clipboard.writeText(parseFloat(activeTrade.amount).toString());
                        setCopiedAmount(true);
                        setTimeout(() => setCopiedAmount(false), 2000);
                      }}
                      style={{ 
                        fontSize: '0.8rem', padding: '0.2rem 0.5rem', borderRadius: 6, 
                        background: copiedAmount ? 'rgba(16,185,129,0.2)' : 'rgba(255,255,255,0.1)',
                        color: copiedAmount ? '#10b981' : 'var(--text-muted)',
                        border: 'none', cursor: 'pointer', transition: 'all 0.2s'
                      }}
                    >
                      {copiedAmount ? '✅ Copied' : '📋 Copy'}
                    </button>
                  )}
                </div>
                <div style={{ fontSize: '0.8rem', color: 'var(--text-muted)', marginTop: '0.25rem' }}>
                  {statusBadge(activeTrade.status)}
                  <span style={{ marginLeft: '0.5rem', fontSize: '0.75rem' }}>
                    ({activeTrade.my_role === 'buyer' ? t('You are buying', 'आप खरीद रहे हैं') : t('You are selling', 'आप बेच रहे हैं')})
                  </span>
                </div>
              </div>
            </div>

            {/* ── BUYER: Step-by-Step Make payment ──────────────────────────── */}
            {activeTrade.my_role === 'buyer' && activeTrade.status === 'pending_payment' && (
              <div>
                {/* Stepper Navigation */}
                <div style={{ display: 'flex', gap: '0.5rem', marginBottom: '1.25rem' }}>
                  <div style={{ flex: 1, height: 4, background: buyerStep >= 1 ? 'var(--gold)' : 'rgba(255,255,255,0.1)', borderRadius: 2, transition: '0.3s' }} />
                  <div style={{ flex: 1, height: 4, background: buyerStep >= 2 ? 'var(--gold)' : 'rgba(255,255,255,0.1)', borderRadius: 2, transition: '0.3s' }} />
                </div>

                <AnimatePresence mode="wait">
                  {buyerStep === 1 ? (
                    <motion.div key="step1" initial={{ opacity: 0, x: -10 }} animate={{ opacity: 1, x: 0 }} exit={{ opacity: 0, x: 10 }}>
                      <div style={{ background: 'rgba(255,255,255,0.03)', padding: '1rem', borderRadius: 12, marginBottom: '1rem', border: '1px solid rgba(255,255,255,0.05)' }}>
                        <p style={{ fontSize: '0.85rem', color: 'var(--text-muted)', marginBottom: '0.4rem' }}>{t('Pay to seller UPI ID:', 'विक्रेता UPI पर भुगतान करें:')}</p>
                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', background: 'rgba(0,0,0,0.3)', padding: '0.75rem', borderRadius: 8 }}>
                          <strong style={{ color: 'var(--gold)', fontSize: '1.1rem', wordBreak: 'break-all' }}>{activeTrade.seller_upi_id}</strong>
                          <button 
                            onClick={() => {
                              navigator.clipboard.writeText(activeTrade.seller_upi_id);
                              setCopiedUpi(true);
                              setTimeout(() => setCopiedUpi(false), 2000);
                            }}
                            style={{ 
                              fontSize: '0.8rem', padding: '0.4rem 0.75rem', borderRadius: 6, 
                              background: copiedUpi ? 'rgba(16,185,129,0.2)' : 'rgba(255,255,255,0.1)',
                              color: copiedUpi ? '#10b981' : 'var(--text-muted)',
                              border: 'none', cursor: 'pointer', transition: 'all 0.2s',
                              whiteSpace: 'nowrap', marginLeft: '0.5rem'
                            }}
                          >
                            {copiedUpi ? '✅ Copied' : '📋 Copy'}
                          </button>
                        </div>
                      </div>

                      <p style={{ fontSize: '0.85rem', fontWeight: 600, marginBottom: '0.75rem', textAlign: 'center' }}>
                        {t('Tap to auto-open Payment App', 'भुगतान ऐप ऑटो-ओपन करने के लिए टैप करें')}
                      </p>
                      <div className="upi-grid" style={{ marginBottom: '1.5rem' }}>
                        {[
                          { id: 'phonepe', name: 'PhonePe', icon: '📱' },
                          { id: 'gpay', name: 'GPay', icon: '💳' },
                          { id: 'paytm', name: 'Paytm', icon: '💰' },
                        ].map((app) => (
                          <button
                            key={app.id}
                            type="button"
                            className="upi-btn"
                            style={{ padding: '0.75rem 0.5rem' }}
                            onClick={() => {
                              openUpiApp(app.id);
                              setBuyerStep(2); // Auto advance to next step
                            }}
                          >
                            <span style={{ fontSize: '1.5rem', marginBottom: '0.2rem' }}>{app.icon}</span>
                            <span style={{ fontWeight: 600, fontSize: '0.8rem' }}>{app.name}</span>
                          </button>
                        ))}
                      </div>
                      
                      <button className="btn btn-ghost btn-full" onClick={() => setBuyerStep(2)}>
                        {t('I have already paid / Next Step →', 'मैंने भुगतान कर दिया है / अगला चरण →')}
                      </button>

                      <button className="btn btn-danger btn-full" onClick={handleCancelTrade} style={{ marginTop: '0.5rem', opacity: 0.8 }} disabled={loading}>
                        {loading ? <span className="spinner" /> : t('Cancel Trade', 'ट्रेड रद्द करें')}
                      </button>
                    </motion.div>
                  ) : (
                    <motion.div key="step2" initial={{ opacity: 0, x: 10 }} animate={{ opacity: 1, x: 0 }} exit={{ opacity: 0, x: -10 }}>
                      <button 
                        className="btn btn-ghost btn-sm" 
                        style={{ marginBottom: '1rem', padding: '0 0.5rem' }}
                        onClick={() => setBuyerStep(1)}
                      >
                        ← {t('Back to Payment Details', 'भुगतान विवरण पर वापस जाएं')}
                      </button>

                      <div className="input-group">
                        <label className="input-label" style={{ fontWeight: 600 }}>{t('12-Digit UTR / Ref Number', '12-अंकीय UTR नंबर')}</label>
                        <input className="input" value={utrNumber} onChange={(e) => setUtrNumber(e.target.value)}
                          placeholder="e.g. 312345678901" maxLength={22} 
                          style={{ fontSize: '1.1rem', letterSpacing: '1px' }} />
                      </div>

                      {/* Screenshot Upload */}
                      <div className="input-group">
                        <label className="input-label" style={{ fontWeight: 600 }}>📸 {t('Payment Screenshot (Required)', 'भुगतान स्क्रीनशॉट (आवश्यक)')}</label>
                        <div style={{
                          border: '2px dashed rgba(255,255,255,0.15)', borderRadius: 12, padding: '1rem',
                          textAlign: 'center', cursor: 'pointer', transition: 'all 0.3s',
                          background: screenshotFile ? 'rgba(16,185,129,0.05)' : 'rgba(0,0,0,0.2)',
                        }}
                          onClick={() => document.getElementById('screenshot-input')?.click()}
                        >
                          <input id="screenshot-input" type="file" accept="image/*" style={{ display: 'none' }}
                            onChange={(e) => {
                              const file = e.target.files?.[0];
                              if (file) {
                                setScreenshotFile(file);
                                setScreenshotPreview(URL.createObjectURL(file));
                              }
                            }} />
                          {screenshotPreview ? (
                            <div>
                              <img src={screenshotPreview} alt="Payment screenshot" style={{
                                maxHeight: 180, borderRadius: 8, marginBottom: '0.75rem', objectFit: 'contain',
                                boxShadow: '0 4px 12px rgba(0,0,0,0.3)'
                              }} />
                              <p style={{ fontSize: '0.85rem', color: 'var(--success)', fontWeight: 600 }}>✅ {t('Screenshot Attached', 'स्क्रीनशॉट संलग्न')}</p>
                            </div>
                          ) : (
                            <div style={{ padding: '1.5rem 0' }}>
                              <div style={{ fontSize: '2.5rem', marginBottom: '0.5rem', opacity: 0.8 }}>📷</div>
                              <p style={{ fontSize: '0.9rem', color: 'var(--text-muted)' }}>
                                {t('Tap to upload payment confirmation screenshot', 'भुगतान पुष्टि स्क्रीनशॉट अपलोड करने के लिए टैप करें')}
                              </p>
                            </div>
                          )}
                        </div>
                      </div>

                      <button className="btn btn-success btn-full btn-lg" onClick={handlePaymentSubmit}
                        disabled={loading || !utrNumber || !screenshotFile}
                        style={{ boxShadow: '0 0 15px rgba(16,185,129,0.3)' }}
                      >
                        {loading ? <span className="spinner" /> : t('Submit Payment Proof', 'भुगतान प्रमाण जमा करें')}
                      </button>
                    </motion.div>
                  )}
                </AnimatePresence>
              </div>
            )}

            {/* ── BUYER: Waiting for seller confirmation ───────── */}
            {activeTrade.my_role === 'buyer' && activeTrade.status === 'payment_submitted' && (
              <div style={{ textAlign: 'center', padding: '1rem 0' }}>
                <div style={{ fontSize: '2rem', marginBottom: '0.5rem' }}>⏳</div>
                <p style={{ fontSize: '0.9rem', color: 'var(--text-muted)' }}>
                  {t('Payment submitted. Waiting for seller to confirm...', 'भुगतान जमा किया। विक्रेता की पुष्टि की प्रतीक्षा...')}
                </p>
                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '0.5rem', marginTop: '0.75rem', color: 'var(--gold)', fontSize: '0.8rem' }}>
                  <span className="spinner" style={{ width: 14, height: 14 }} />
                  {t('Live — you will be notified instantly', 'लाइव — आपको तुरंत सूचित किया जाएगा')}
                </div>
              </div>
            )}

            {/* ── SELLER: Waiting for buyer payment ───────────── */}
            {activeTrade.my_role === 'seller' && activeTrade.status === 'pending_payment' && (
              <div style={{ textAlign: 'center', padding: '1rem 0' }}>
                <div style={{ fontSize: '2rem', marginBottom: '0.5rem' }}>⏳</div>
                <p style={{ fontSize: '0.9rem', color: 'var(--text-muted)' }}>
                  {t('Buyer matched! Waiting for their payment...', 'खरीदार मिला! उनके भुगतान की प्रतीक्षा...')}
                </p>
                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '0.5rem', marginTop: '0.75rem', color: 'var(--gold)', fontSize: '0.8rem' }}>
                  <span className="spinner" style={{ width: 14, height: 14 }} />
                  {t('Live — you will be notified when they pay', 'लाइव — भुगतान होने पर आपको सूचित किया जाएगा')}
                </div>
                <button className="btn btn-danger btn-sm" onClick={handleCancelSellOrder} style={{ marginTop: '1.25rem', opacity: 0.8 }} disabled={loading}>
                  {loading ? <span className="spinner" /> : t('Request Cancel', 'रद्द करने का अनुरोध करें')}
                </button>
              </div>
            )}

            {/* ── SELLER: Confirm or reject payment ───────────── */}
            {activeTrade.my_role === 'seller' && activeTrade.status === 'payment_submitted' && (
              <div>
                <div style={{ background: 'rgba(255,255,255,0.03)', padding: '1rem', borderRadius: 12, marginBottom: '1.25rem', border: '1px solid rgba(255,255,255,0.05)' }}>
                  <p style={{ fontSize: '0.85rem', color: 'var(--text-muted)', marginBottom: '0.4rem' }}>
                    {t('Buyer claims paid. UTR Number:', 'खरीदार ने भुगतान का दावा किया। UTR:')}
                  </p>
                  <strong style={{ color: 'var(--gold)', fontSize: '1.2rem', letterSpacing: '1px' }}>{activeTrade.utr_number}</strong>
                </div>

                {/* Show buyer's payment screenshot (Large inline) */}
                {activeTrade.buyer_payment_screenshot_url && (
                  <div style={{ marginBottom: '1.5rem', textAlign: 'center' }}>
                    <p style={{ fontSize: '0.85rem', fontWeight: 600, marginBottom: '0.75rem' }}>
                      📸 {t('Buyer\'s Payment Screenshot', 'खरीदार का भुगतान स्क्रीनशॉट')}
                    </p>
                    <div style={{ background: '#000', borderRadius: 12, padding: '0.5rem', border: '1px solid rgba(255,255,255,0.1)' }}>
                      <img src={getFileUrl(activeTrade.buyer_payment_screenshot_url)} alt="Payment proof"
                        style={{ width: '100%', maxHeight: 350, objectFit: 'contain', borderRadius: 8 }} />
                    </div>
                  </div>
                )}

                <div style={{ padding: '1rem', background: 'rgba(16,185,129,0.1)', borderRadius: 12, marginBottom: '1.5rem', border: '1px solid rgba(16,185,129,0.2)', textAlign: 'center' }}>
                  <p style={{ fontSize: '0.9rem', color: '#10b981', fontWeight: 600, marginBottom: '0.25rem' }}>
                    {t('Check your Bank/UPI App!', 'अपना बैंक/UPI ऐप जांचें!')}
                  </p>
                  <p style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>
                    {t('Verify that you actually received the exact amount before confirming.', 'पुष्टि करने से पहले सत्यापित करें कि आपको वास्तव में सटीक राशि प्राप्त हुई है।')}
                  </p>
                </div>

                <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
                  <button 
                    className="btn btn-success btn-full btn-lg" 
                    onClick={handleConfirm} 
                    disabled={loading}
                    style={{ fontSize: '1.1rem', padding: '1rem', boxShadow: '0 0 20px rgba(16,185,129,0.4)' }}
                  >
                    ✅ {t('Yes, I received payment', 'हाँ, मुझे भुगतान प्राप्त हुआ')}
                  </button>
                  
                  {!showRejectModal && (
                    <button 
                      className="btn btn-ghost btn-sm" 
                      onClick={() => setShowRejectModal(true)} 
                      disabled={loading}
                      style={{ color: '#ef4444', alignSelf: 'center' }}
                    >
                      {t('Did not receive? Reject Payment', 'भुगतान नहीं मिला? अस्वीकार करें')}
                    </button>
                  )}
                </div>

                {/* Reject Modal — requires screen recording */}
                {showRejectModal && (
                  <motion.div
                    initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }}
                    style={{
                      marginTop: '1rem', padding: '1rem', borderRadius: 12,
                      background: 'rgba(239,68,68,0.08)', border: '1px solid rgba(239,68,68,0.2)',
                    }}
                  >
                    <p style={{ fontSize: '0.85rem', fontWeight: 600, marginBottom: '0.75rem', color: '#ef4444' }}>
                      ⚠️ {t('Upload Screen Recording to Reject', 'अस्वीकार करने के लिए स्क्रीन रिकॉर्डिंग अपलोड करें')}
                    </p>
                    <p style={{ fontSize: '0.78rem', color: 'var(--text-muted)', marginBottom: '0.75rem' }}>
                      {t(
                        'Record your UPI/banking app showing your profile and latest 5 transactions to prove payment was not received.',
                        'यह साबित करने के लिए कि भुगतान नहीं मिला, अपने UPI/बैंकिंग ऐप में प्रोफ़ाइल और नवीनतम 5 लेनदेन दिखाते हुए रिकॉर्ड करें।'
                      )}
                    </p>
                    {/* Screen Recording Upload */}
                    <div style={{
                      border: '2px dashed rgba(239,68,68,0.3)', borderRadius: 12, padding: '1rem',
                      textAlign: 'center', cursor: 'pointer', marginBottom: '0.75rem',
                      background: rejectRecording ? 'rgba(239,68,68,0.05)' : 'transparent',
                    }}
                      onClick={() => document.getElementById('reject-recording-input')?.click()}
                    >
                      <input id="reject-recording-input" type="file" accept="video/*" style={{ display: 'none' }}
                        onChange={(e) => setRejectRecording(e.target.files?.[0] || null)} />
                      {rejectRecording ? (
                        <p style={{ fontSize: '0.8rem', color: 'var(--success)' }}>🎥 ✅ {rejectRecording.name} ({(rejectRecording.size / (1024*1024)).toFixed(1)}MB)</p>
                      ) : (
                        <div>
                          <div style={{ fontSize: '2rem', marginBottom: '0.5rem' }}>🎥</div>
                          <p style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>
                            {t('Tap to upload screen recording (Required)', 'स्क्रीन रिकॉर्डिंग अपलोड करें (आवश्यक)')}
                          </p>
                        </div>
                      )}
                    </div>
                    {/* Bank Statement Upload */}
                    <div style={{
                      border: '2px dashed rgba(245,158,11,0.3)', borderRadius: 12, padding: '0.75rem',
                      textAlign: 'center', cursor: 'pointer',
                      background: rejectBankStatement ? 'rgba(245,158,11,0.05)' : 'transparent',
                    }}
                      onClick={() => document.getElementById('reject-bankstmt-input')?.click()}
                    >
                      <input id="reject-bankstmt-input" type="file" accept="application/pdf,image/*" style={{ display: 'none' }}
                        onChange={(e) => setRejectBankStatement(e.target.files?.[0] || null)} />
                      {rejectBankStatement ? (
                        <p style={{ fontSize: '0.8rem', color: 'var(--success)' }}>🏦 ✅ {rejectBankStatement.name}</p>
                      ) : (
                        <div>
                          <div style={{ fontSize: '1.5rem', marginBottom: '0.3rem' }}>🏦</div>
                          <p style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>
                            {t('Upload bank statement (Optional)', 'बैंक स्टेटमेंट अपलोड करें (वैकल्पिक)')}
                          </p>
                        </div>
                      )}
                    </div>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.5rem', marginTop: '0.75rem' }}>
                      <button className="btn btn-ghost btn-sm" onClick={() => { setShowRejectModal(false); setRejectRecording(null); setRejectBankStatement(null); }}>
                        {t('Cancel', 'रद्द करें')}
                      </button>
                      <button className="btn btn-danger btn-sm" onClick={handleReject} disabled={loading || !rejectRecording}>
                        {loading ? <span className="spinner" /> : t('Confirm Reject', 'अस्वीकार पुष्टि')}
                      </button>
                    </div>
                  </motion.div>
                )}
              </div>
            )}

            {/* ── BUYER: Seller rejected — Appeal option ───────── */}
            {activeTrade.my_role === 'buyer' && activeTrade.status === 'seller_rejected' && (
              <div>
                <div style={{
                  background: 'rgba(239,68,68,0.1)', border: '1px solid rgba(239,68,68,0.3)',
                  borderRadius: 12, padding: '1rem', marginBottom: '1rem', textAlign: 'center',
                }}>
                  <div style={{ fontSize: '2rem', marginBottom: '0.5rem' }}>⚠️</div>
                  <p style={{ fontSize: '0.9rem', fontWeight: 600, color: '#ef4444', marginBottom: '0.5rem' }}>
                    {t('Seller rejected your payment', 'विक्रेता ने आपका भुगतान अस्वीकार किया')}
                  </p>
                  <p style={{ fontSize: '0.8rem', color: 'var(--text-muted)', marginBottom: '1rem' }}>
                    {t(
                      'Upload your bank statement and screen recording as proof to appeal this decision.',
                      'इस निर्णय की अपील के लिए अपना बैंक स्टेटमेंट और स्क्रीन रिकॉर्डिंग प्रमाण के रूप में अपलोड करें।'
                    )}
                  </p>
                  {!showAppealModal ? (
                    <button className="btn btn-primary btn-lg btn-full" onClick={() => setShowAppealModal(true)}>
                      🛡️ {t('Appeal with Proof', 'प्रमाण के साथ अपील करें')}
                    </button>
                  ) : (
                    <motion.div initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }}>
                      {/* Screen Recording Upload */}
                      <p style={{ fontSize: '0.78rem', color: 'var(--text-muted)', marginBottom: '0.5rem', textAlign: 'left' }}>
                        {t('Record your UPI/banking app showing the successful payment to the seller.', 'विक्रेता को सफल भुगतान दिखाते हुए अपने UPI/बैंकिंग ऐप की रिकॉर्डिंग करें।')}
                      </p>
                      <div style={{
                        border: '2px dashed rgba(59,130,246,0.3)', borderRadius: 12, padding: '0.75rem',
                        textAlign: 'center', cursor: 'pointer', marginBottom: '0.75rem',
                        background: appealRecording ? 'rgba(59,130,246,0.05)' : 'transparent',
                      }}
                        onClick={() => document.getElementById('appeal-recording-input')?.click()}
                      >
                        <input id="appeal-recording-input" type="file" accept="video/*" style={{ display: 'none' }}
                          onChange={(e) => setAppealRecording(e.target.files?.[0] || null)} />
                        {appealRecording ? (
                          <p style={{ fontSize: '0.8rem', color: 'var(--success)' }}>🎥 ✅ {appealRecording.name} ({(appealRecording.size / (1024*1024)).toFixed(1)}MB)</p>
                        ) : (
                          <div>
                            <div style={{ fontSize: '1.5rem', marginBottom: '0.3rem' }}>🎥</div>
                            <p style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>{t('Upload screen recording', 'स्क्रीन रिकॉर्डिंग अपलोड करें')}</p>
                          </div>
                        )}
                      </div>
                      {/* Bank Statement Upload */}
                      <div style={{
                        border: '2px dashed rgba(245,158,11,0.3)', borderRadius: 12, padding: '0.75rem',
                        textAlign: 'center', cursor: 'pointer', marginBottom: '0.75rem',
                        background: appealBankStatement ? 'rgba(245,158,11,0.05)' : 'transparent',
                      }}
                        onClick={() => document.getElementById('appeal-bankstmt-input')?.click()}
                      >
                        <input id="appeal-bankstmt-input" type="file" accept="application/pdf,image/*" style={{ display: 'none' }}
                          onChange={(e) => setAppealBankStatement(e.target.files?.[0] || null)} />
                        {appealBankStatement ? (
                          <p style={{ fontSize: '0.8rem', color: 'var(--success)' }}>🏦 ✅ {appealBankStatement.name}</p>
                        ) : (
                          <div>
                            <div style={{ fontSize: '1.5rem', marginBottom: '0.3rem' }}>🏦</div>
                            <p style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>{t('Upload bank statement (PDF/Image)', 'बैंक स्टेटमेंट अपलोड करें (PDF/इमेज)')}</p>
                          </div>
                        )}
                      </div>
                      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.5rem' }}>
                        <button className="btn btn-ghost btn-sm" onClick={() => { setShowAppealModal(false); setAppealRecording(null); setAppealBankStatement(null); }}>
                          {t('Cancel', 'रद्द करें')}
                        </button>
                        <button className="btn btn-primary btn-sm" onClick={handleAppeal}
                          disabled={loading || (!appealRecording && !appealBankStatement)}>
                          {loading ? <span className="spinner" /> : t('Submit Appeal', 'अपील जमा करें')}
                        </button>
                      </div>
                    </motion.div>
                  )}
                </div>
              </div>
            )}

            {/* ── BOTH: Disputed — waiting for assistance ────── */}
            {activeTrade.status === 'disputed' && (
              <div style={{ textAlign: 'center', padding: '1rem 0' }}>
                <div style={{ fontSize: '2rem', marginBottom: '0.5rem' }}>{disputeInfo?.status === 'escalated' ? '🚨' : '⚖️'}</div>
                <p style={{ fontSize: '0.9rem', fontWeight: 600, color: disputeInfo?.status === 'escalated' ? '#ef4444' : '#f59e0b', marginBottom: '0.5rem' }}>
                  {disputeInfo?.status === 'escalated' 
                    ? t('Escalated to Super Admin', 'सुपर एडमिन को भेजा गया') 
                    : t('Dispute Under Review', 'विवाद समीक्षा में')}
                </p>
                <p style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>
                  {disputeInfo?.status === 'escalated'
                    ? t('This dispute requires higher-level review and has been escalated to a Super Admin. Please wait.', 'इस विवाद के लिए उच्च-स्तरीय समीक्षा की आवश्यकता है और इसे सुपर एडमिन को भेज दिया गया है। कृपया प्रतीक्षा करें।')
                    : t('Both proofs have been submitted. Our AI is analyzing the evidence and an assistant will review shortly.', 'दोनों प्रमाण जमा किए गए हैं। हमारा AI सबूतों का विश्लेषण कर रहा है और एक सहायक जल्द ही समीक्षा करेगा।')}
                </p>
                {disputeInfo?.buyer_ai_score != null && disputeInfo?.seller_ai_score != null && (
                  <div style={{ marginTop: '0.75rem', fontSize: '0.8rem' }}>
                    <span style={{ color: '#3b82f6' }}>{t('Your Score:', 'आपका स्कोर:')} <strong>{disputeInfo.buyer_ai_score}%</strong></span>
                    <span style={{ margin: '0 0.5rem', color: 'var(--text-muted)' }}>vs</span>
                    <span style={{ color: '#f59e0b' }}>{t('Seller Score:', 'विक्रेता स्कोर:')} <strong>{disputeInfo.seller_ai_score}%</strong></span>
                  </div>
                )}
                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '0.5rem', marginTop: '0.75rem', color: 'var(--gold)', fontSize: '0.8rem' }}>
                  <span className="spinner" style={{ width: 14, height: 14 }} />
                  {t('You will be notified of the result', 'आपको परिणाम की सूचना दी जाएगी')}
                </div>
              </div>
            )}
          </div>
        )}
        </div>

        {/* Right Column: History */}
        <div>
        {/* Trade History */}
        <div className="card glass">
          <p className="section-title">📋 {t('Trade History', 'ट्रेड इतिहास')}</p>
          {history.length === 0 ? (
            <p style={{ textAlign: 'center', padding: '1rem', fontSize: '0.9rem' }}>
              {t('No trades yet. Start trading!', 'अभी कोई ट्रेड नहीं। ट्रेडिंग शुरू करें!')}
            </p>
          ) : history.map((h) => (
            <div
              key={h.id}
              className="list-item"
              style={{ cursor: 'pointer', transition: 'background 0.2s', padding: '0.75rem' }}
              onClick={() => setSelectedHistoryTrade(h)}
            >
              <div>
                <div style={{ fontWeight: 600, fontSize: '0.9rem', display: 'flex', alignItems: 'center', gap: '0.4rem' }}>
                  <span>{h.my_role === 'buyer' ? '📥' : '📤'}</span>
                  <span>₹{parseFloat(h.amount).toLocaleString()}</span>
                  <span style={{ fontSize: '0.75rem', color: 'var(--text-muted)', fontWeight: 400 }}>
                    ({h.my_role === 'buyer' ? t('Bought', 'खरीदा') : t('Sold', 'बेचा')})
                  </span>
                </div>
                <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: '0.2rem' }}>
                  {new Date(h.matched_at).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })}
                  {h.utr_number && <span style={{ marginLeft: '0.4rem', color: 'var(--gold)' }}>• UTR: {h.utr_number.slice(-6)}</span>}
                </div>
              </div>
              <div style={{ textAlign: 'right', display: 'flex', flexDirection: 'column', alignItems: 'flex-end', gap: '0.2rem' }}>
                {statusBadge(h.status)}
                <span style={{ fontSize: '0.7rem', color: 'var(--gold)', display: 'flex', alignItems: 'center', gap: '0.2rem' }}>
                  🔍 {t('Details', 'विवरण')}
                </span>
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>

    {/* Order Details Modal */}
    <AnimatePresence>
      {selectedHistoryTrade && (
        <div
          style={{
            position: 'fixed', inset: 0, zIndex: 1000,
            background: 'rgba(0,0,0,0.8)', backdropFilter: 'blur(8px)',
            display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '1rem',
          }}
          onClick={() => setSelectedHistoryTrade(null)}
        >
          <motion.div
            initial={{ opacity: 0, scale: 0.95 }}
            animate={{ opacity: 1, scale: 1 }}
            exit={{ opacity: 0, scale: 0.95 }}
            className="card glass card-glow"
            style={{ maxWidth: 480, width: '100%', maxHeight: '90vh', overflowY: 'auto', position: 'relative' }}
            onClick={(e) => e.stopPropagation()}
          >
            <button
              style={{
                position: 'absolute', top: 12, right: 12, background: 'none', border: 'none',
                color: 'var(--text-muted)', fontSize: '1.5rem', cursor: 'pointer', zIndex: 10,
              }}
              onClick={() => setSelectedHistoryTrade(null)}
            >
              ✕
            </button>

            <h3 style={{ fontSize: '1.2rem', marginBottom: '0.25rem', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
              {selectedHistoryTrade.my_role === 'buyer' ? '📥 Buy Order Details' : '📤 Sell Order Details'}
            </h3>
            <p style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginBottom: '1rem', wordBreak: 'break-all' }}>
              Trade ID: {selectedHistoryTrade.id}
            </p>

            <div style={{ background: 'rgba(255,255,255,0.03)', border: '1px solid rgba(255,255,255,0.08)', borderRadius: 12, padding: '1rem', marginBottom: '1rem', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <div>
                <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>Amount</div>
                <div style={{ fontSize: '1.6rem', fontWeight: 700, color: 'var(--gold)' }}>
                  ₹{parseFloat(selectedHistoryTrade.amount).toLocaleString()}
                </div>
              </div>
              <div style={{ textAlign: 'right' }}>
                <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginBottom: '0.25rem' }}>Status</div>
                {statusBadge(selectedHistoryTrade.status)}
              </div>
            </div>

            {/* Counterparty Information */}
            <div style={{ background: 'rgba(255,255,255,0.03)', border: '1px solid rgba(255,255,255,0.08)', borderRadius: 12, padding: '1rem', marginBottom: '1rem' }}>
              <p style={{ fontSize: '0.85rem', fontWeight: 600, color: 'var(--gold)', marginBottom: '0.5rem' }}>
                👤 Counterparty Info
              </p>
              {selectedHistoryTrade.my_role === 'buyer' ? (
                <div style={{ fontSize: '0.82rem', display: 'flex', flexDirection: 'column', gap: '0.3rem' }}>
                  <div>Seller Name: <strong>{selectedHistoryTrade.seller_name || 'N/A'}</strong></div>
                  {selectedHistoryTrade.seller_mobile && (
                    <div style={{ color: 'var(--text-muted)' }}>
                      Seller Mobile: 📱 {selectedHistoryTrade.seller_mobile.slice(0, 3)}****{selectedHistoryTrade.seller_mobile.slice(-3)}
                    </div>
                  )}
                  {selectedHistoryTrade.seller_upi_id && (
                    <div style={{ color: 'var(--text-muted)' }}>
                      Seller UPI ID: <strong style={{ color: 'var(--gold)' }}>{selectedHistoryTrade.seller_upi_id}</strong>
                    </div>
                  )}
                </div>
              ) : (
                <div style={{ fontSize: '0.82rem', display: 'flex', flexDirection: 'column', gap: '0.3rem' }}>
                  <div>Buyer Name: <strong>{selectedHistoryTrade.buyer_name || 'N/A'}</strong></div>
                  {selectedHistoryTrade.buyer_mobile && (
                    <div style={{ color: 'var(--text-muted)' }}>
                      Buyer Mobile: 📱 {selectedHistoryTrade.buyer_mobile.slice(0, 3)}****{selectedHistoryTrade.buyer_mobile.slice(-3)}
                    </div>
                  )}
                </div>
              )}
            </div>

            {/* UTR Number */}
            {selectedHistoryTrade.utr_number && (
              <div style={{ background: 'rgba(255,255,255,0.03)', border: '1px solid rgba(255,255,255,0.08)', borderRadius: 12, padding: '0.85rem 1rem', marginBottom: '1rem', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <div>
                  <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>UTR Number</div>
                  <div style={{ fontSize: '1rem', fontWeight: 700, letterSpacing: 1, fontFamily: 'monospace', color: 'var(--gold)' }}>
                    {selectedHistoryTrade.utr_number}
                  </div>
                </div>
                <button
                  type="button"
                  className="btn btn-sm btn-ghost"
                  onClick={() => {
                    navigator.clipboard.writeText(selectedHistoryTrade.utr_number);
                    setCopiedUtr(true);
                    setTimeout(() => setCopiedUtr(false), 2000);
                  }}
                >
                  {copiedUtr ? '✅ Copied' : '📋 Copy'}
                </button>
              </div>
            )}

            {/* Payment Screenshot */}
            {selectedHistoryTrade.buyer_payment_screenshot_url && (
              <div style={{ marginBottom: '1rem' }}>
                <p style={{ fontSize: '0.85rem', fontWeight: 600, marginBottom: '0.5rem' }}>
                  📸 Payment Proof Screenshot
                </p>
                <div style={{ border: '1px solid rgba(255,255,255,0.1)', borderRadius: 8, overflow: 'hidden', textAlign: 'center', background: '#000' }}>
                  <img
                    src={getFileUrl(selectedHistoryTrade.buyer_payment_screenshot_url)}
                    alt="Payment proof screenshot"
                    style={{ width: '100%', maxHeight: 280, objectFit: 'contain' }}
                  />
                </div>
              </div>
            )}

            {/* Dates Timeline */}
            <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', borderTop: '1px solid rgba(255,255,255,0.1)', paddingTop: '0.75rem', display: 'flex', flexDirection: 'column', gap: '0.25rem' }}>
              {selectedHistoryTrade.matched_at && <div>Matched At: {new Date(selectedHistoryTrade.matched_at).toLocaleString('en-IN')}</div>}
              {selectedHistoryTrade.paid_at && <div>Paid At: {new Date(selectedHistoryTrade.paid_at).toLocaleString('en-IN')}</div>}
              {selectedHistoryTrade.completed_at && <div>Completed At: {new Date(selectedHistoryTrade.completed_at).toLocaleString('en-IN')}</div>}
            </div>
          </motion.div>
        </div>
      )}
    </AnimatePresence>
    </div>
  );
}

export default function TradeModule() {
  return (
    <Suspense fallback={
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '2rem' }}>
        <div className="spinner" style={{ width: 48, height: 48, borderColor: 'var(--gold) transparent var(--gold) transparent' }} />
      </div>
    }>
      <TradeModuleInner />
    </Suspense>
  );
}
