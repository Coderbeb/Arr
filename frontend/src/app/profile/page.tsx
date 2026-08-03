'use client';
import { useState, useEffect, useCallback } from 'react';
import { useRouter } from 'next/navigation';
import axios from 'axios';
import AppLayout from '@/components/AppLayout';
import { useLanguage } from '@/contexts/LanguageContext';
import { motion, AnimatePresence } from 'framer-motion';

const API = process.env.NEXT_PUBLIC_API_URL || '/api';

export default function ProfilePage() {
  const router = useRouter();
  const { lang, setLang, t } = useLanguage();
  const [user, setUser] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [upiId, setUpiId] = useState('');
  const [upiApp, setUpiApp] = useState('');
  const [success, setSuccess] = useState('');
  const [error, setError] = useState('');

  const token = typeof window !== 'undefined' ? localStorage.getItem('token') : '';
  const headers = { Authorization: `Bearer ${token}` };

  const fetchProfile = useCallback(async () => {
    if (!token) { router.push('/'); return; }
    try {
      const res = await axios.get(`${API}/auth/me`, { headers });
      setUser(res.data);
      setUpiId(res.data.upi_id || '');
      setUpiApp(res.data.upi_app || '');
    } catch { router.push('/'); }
    finally { setLoading(false); }
  }, []);

  useEffect(() => { fetchProfile(); }, [fetchProfile]);

  async function saveUpi() {
    setSaving(true); setError(''); setSuccess('');
    try {
      await axios.put(`${API}/auth/upi-profile`, { upi_id: upiId, upi_app: upiApp }, { headers });
      setSuccess(t('UPI profile saved!', 'UPI प्रोफ़ाइल सहेजी गई!'));
    } catch (err: any) { setError(err.response?.data?.error || err.message || 'Failed'); }
    finally { setSaving(false); }
  }

  async function toggleLang() {
    const newLang = lang === 'en' ? 'hi' : 'en';
    try {
      await axios.put(`${API}/auth/language`, { language: newLang }, { headers });
      setLang(newLang);
    } catch {}
  }

  if (loading) return (
    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', minHeight: '100vh' }}>
      <div className="spinner" style={{ width: 48, height: 48 }} />
    </div>
  );

  const containerVariants = { hidden: { opacity: 0 }, show: { opacity: 1, transition: { staggerChildren: 0.1 } } };
  const itemVariants = { hidden: { opacity: 0, y: 20 }, show: { opacity: 1, y: 0 } };

  return (
    <AppLayout title={t('Profile', 'प्रोफ़ाइल')} role={user?.role || 'user'}>
      <motion.div variants={containerVariants} initial="hidden" animate="show" style={{ display: 'grid', gap: '1.25rem', gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))' }}>
        
        {/* Left Column: User Info */}
        <div>
        {/* User Info */}
        <motion.div variants={itemVariants} className="card glass" style={{ marginBottom: '1.25rem', textAlign: 'center' }}>
          <div style={{ width: 80, height: 80, borderRadius: '50%', background: 'linear-gradient(135deg, var(--gold), var(--gold-dark))', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 1rem', fontSize: '2.5rem', boxShadow: '0 8px 32px rgba(245,166,35,0.4)' }}>
            {user?.full_name?.charAt(0)?.toUpperCase()}
          </div>
          <h2 style={{ marginBottom: '0.25rem' }}>{user?.full_name}</h2>
          <p style={{ fontSize: '0.85rem' }}>📱 {user?.mobile_number}</p>
          {user?.email && <p style={{ fontSize: '0.8rem' }}>✉️ {user.email}</p>}

          <div style={{ display: 'flex', justifyContent: 'center', gap: '1rem', marginTop: '1.5rem' }}>
            <div className="badge badge-gold glass" style={{ padding: '0.4rem 0.8rem' }}>⭐ {user?.reputation_score}%</div>
            <div className="badge badge-info glass" style={{ padding: '0.4rem 0.8rem' }}>📊 {user?.total_trades} {t('trades', 'ट्रेड')}</div>
            {user?.strike_count > 0 && <div className="badge badge-danger glass" style={{ padding: '0.4rem 0.8rem' }}>⚠️ {user.strike_count} {t('strikes', 'स्ट्राइक')}</div>}
          </div>
        </motion.div>
        </div>

        {/* Right Column: Settings */}
        <div>
        {/* UPI Settings */}
        <motion.div variants={itemVariants} className="card glass" style={{ marginBottom: '1.25rem' }}>
          <p className="section-title">💳 {t('UPI Settings', 'UPI सेटिंग')}</p>

          <AnimatePresence>
            {success && <motion.div initial={{ opacity: 0, height: 0 }} animate={{ opacity: 1, height: 'auto' }} exit={{ opacity: 0, height: 0 }} style={{ background: 'rgba(34,197,94,0.1)', border: '1px solid rgba(34,197,94,0.3)', borderRadius: 8, padding: '0.75rem', marginBottom: '1rem', color: '#22c55e', fontSize: '0.85rem', overflow: 'hidden' }}>{success}</motion.div>}
            {error && <motion.div initial={{ opacity: 0, height: 0 }} animate={{ opacity: 1, height: 'auto' }} exit={{ opacity: 0, height: 0 }} style={{ background: 'rgba(239,68,68,0.1)', border: '1px solid rgba(239,68,68,0.3)', borderRadius: 8, padding: '0.75rem', marginBottom: '1rem', color: '#ef4444', fontSize: '0.85rem', overflow: 'hidden' }}>{error}</motion.div>}
          </AnimatePresence>

          <div className="input-group">
            <label className="input-label">{t('UPI ID', 'UPI आईडी')}</label>
            <input className="input" value={upiId} onChange={(e) => setUpiId(e.target.value)} placeholder="yourname@upi" />
          </div>

          <div className="input-group">
            <label className="input-label">{t('Preferred UPI App', 'पसंदीदा UPI ऐप')}</label>
            <div className="upi-grid">
              {['gpay', 'phonepe', 'paytm', 'bhim'].map((app) => (
                <button key={app} className={`upi-btn ${upiApp === app ? 'selected' : ''}`} onClick={() => setUpiApp(app)}>
                  <span style={{ fontSize: '1.3rem' }}>
                    {app === 'gpay' ? '💳' : app === 'phonepe' ? '📱' : app === 'paytm' ? '💰' : '🏛️'}
                  </span>
                  {app.charAt(0).toUpperCase() + app.slice(1)}
                </button>
              ))}
            </div>
          </div>

          <button className="btn btn-primary btn-full" onClick={saveUpi} disabled={saving || !upiId}>
            {saving ? <span className="spinner" /> : t('Save UPI Profile', 'UPI प्रोफ़ाइल सहेजें')}
          </button>
        </motion.div>

        {/* Language */}
        <motion.div variants={itemVariants} className="card glass" style={{ marginBottom: '1.25rem' }}>
          <p className="section-title">🌐 {t('Language', 'भाषा')}</p>
          <div style={{ display: 'flex', gap: '0.75rem' }}>
            <button className={`btn btn-sm ${lang === 'en' ? 'btn-primary' : 'btn-ghost glass'}`} style={{ flex: 1 }} onClick={() => lang !== 'en' && toggleLang()}>
              🇬🇧 English
            </button>
            <button className={`btn btn-sm ${lang === 'hi' ? 'btn-primary' : 'btn-ghost glass'}`} style={{ flex: 1 }} onClick={() => lang !== 'hi' && toggleLang()}>
              🇮🇳 हिंदी
            </button>
          </div>
        </motion.div>

        </div>
      </motion.div>
    </AppLayout>
  );
}
