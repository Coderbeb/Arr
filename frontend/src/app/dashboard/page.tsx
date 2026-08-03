'use client';
import { useState, useEffect, useCallback } from 'react';
import { useRouter } from 'next/navigation';
import axios from 'axios';
import AppLayout from '@/components/AppLayout';
import CountUp from '@/components/CountUp';
import { useLanguage } from '@/contexts/LanguageContext';
import { motion } from 'framer-motion';

const API = process.env.NEXT_PUBLIC_API_URL || '/api';

export default function Dashboard() {
  const router = useRouter();
  const { lang, t } = useLanguage();
  const [user, setUser] = useState<any>(null);
  const [wallet, setWallet] = useState<any>(null);
  const [txns, setTxns] = useState<any[]>([]);
  const [notifications, setNotifications] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  const fetchData = useCallback(async () => {
    const token = localStorage.getItem('token');
    if (!token) { router.push('/'); return; }
    try {
      const headers = { Authorization: `Bearer ${token}` };
      const [userRes, walletRes, txnRes, notifRes] = await Promise.all([
        axios.get(`${API}/auth/me`, { headers }),
        axios.get(`${API}/wallet/balance`, { headers }),
        axios.get(`${API}/wallet/transactions?lang=${lang}`, { headers }),
        axios.get(`${API}/wallet/notifications?lang=${lang}`, { headers }),
      ]);
      setUser(userRes.data);
      setWallet(walletRes.data);
      setTxns(txnRes.data.slice(0, 10));
      setNotifications(notifRes.data.filter((n: any) => !n.is_read).slice(0, 5));
    } catch { router.push('/'); }
    finally { setLoading(false); }
  }, [lang, router]);

  useEffect(() => { fetchData(); }, [fetchData]);

  if (loading) return (
    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', minHeight: '100vh' }}>
      <div style={{ textAlign: 'center' }}>
        <div className="spinner" style={{ width: 48, height: 48, margin: '0 auto 1rem' }} />
        <p>Loading...</p>
      </div>
    </div>
  );

  const dailyPct = wallet ? Math.min(100, (wallet.daily_earned / wallet.max_daily_earning) * 100) : 0;
  const unreadCount = notifications.length;

  const containerVariants = {
    hidden: { opacity: 0 },
    show: { opacity: 1, transition: { staggerChildren: 0.1 } }
  };
  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    show: { opacity: 1, y: 0 }
  };

  return (
    <AppLayout title={t('Dashboard', 'डैशबोर्ड')}>
      <motion.div variants={containerVariants} initial="hidden" animate="show">
        {/* Balance Card */}
        <motion.div variants={itemVariants} className="balance-card card-glow glass" style={{ marginBottom: '1.25rem' }}>
          <div className="balance-label">{t('Wallet Balance', 'वॉलेट बैलेंस')}</div>
          <div className="balance-amount">
            ₹<CountUp target={parseFloat(wallet?.wallet_balance || 0)} />
          </div>
          {wallet?.escrow_balance > 0 && (
            <div style={{ marginTop: '0.5rem', fontSize: '0.8rem', color: 'var(--warning)' }}>
              🔒 ₹{parseFloat(wallet.escrow_balance).toFixed(2)} {t('in escrow', 'एस्क्रो में')}
            </div>
          )}

          {/* Daily earnings progress */}
          <div style={{ marginTop: '1.25rem' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.78rem', color: 'var(--text-muted)', marginBottom: '0.4rem' }}>
              <span>{t("Today's Earnings", 'आज की कमाई')}</span>
              <span>₹{parseFloat(wallet?.daily_earned || 0).toFixed(2)} / ₹{wallet?.max_daily_earning}</span>
            </div>
            <div className="progress-bar">
              <motion.div className="progress-fill" initial={{ width: 0 }} animate={{ width: `${dailyPct}%` }} transition={{ duration: 1, ease: 'easeOut' }} />
            </div>
          </div>
        </motion.div>

        {/* Stats Row */}
        <motion.div variants={itemVariants} style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '0.75rem', marginBottom: '1.25rem' }}>
          {[
            { label: t('Trades', 'ट्रेड'), value: user?.total_trades || 0, icon: '📊' },
            { label: t('Reputation', 'प्रतिष्ठा'), value: `${user?.reputation_score || 100}%`, icon: '⭐' },
            { label: t('Strikes', 'स्ट्राइक'), value: user?.strike_count || 0, icon: '⚠️' },
          ].map((stat) => (
            <div key={stat.label} className="card glass" style={{ textAlign: 'center', padding: '1rem 0.5rem' }}>
              <div style={{ fontSize: '1.4rem', marginBottom: '0.25rem' }}>{stat.icon}</div>
              <div style={{ fontSize: '1.1rem', fontWeight: 700, color: 'var(--text-primary)' }}>{stat.value}</div>
              <div style={{ fontSize: '0.7rem', color: 'var(--text-muted)', marginTop: '0.1rem' }}>{stat.label}</div>
            </div>
          ))}
        </motion.div>

        {/* Trade Buttons */}
        <motion.div variants={itemVariants} className="card glass" style={{ marginBottom: '1.25rem' }}>
          <p className="section-title">{t('Quick Trade', 'त्वरित ट्रेड')}</p>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.75rem' }}>
            <button className="btn btn-primary btn-lg" onClick={() => router.push('/trade?mode=buy')}>
              📥 {t('Buy Coins', 'खरीदें')}
            </button>
            <button className="btn btn-ghost btn-lg glass" onClick={() => router.push('/trade?mode=sell')}>
              📤 {t('Sell Coins', 'बेचें')}
            </button>
          </div>
        </motion.div>

        {/* Notifications */}
        {notifications.length > 0 && (
          <motion.div variants={itemVariants} className="card glass" style={{ marginBottom: '1.25rem' }}>
            <p className="section-title">🔔 {t('Notifications', 'सूचनाएं')}</p>
            {notifications.map((n) => (
              <div key={n.id} className="list-item" onClick={() => n.trade_id && router.push(`/trade/${n.trade_id}`)}>
                <div>
                  <div style={{ fontWeight: 600, fontSize: '0.9rem' }}>{n.title}</div>
                  <div style={{ fontSize: '0.8rem', color: 'var(--text-muted)', marginTop: '0.2rem' }}>{n.body}</div>
                </div>
                <span style={{ color: 'var(--text-muted)', fontSize: '0.8rem' }}>›</span>
              </div>
            ))}
          </motion.div>
        )}

        {/* Recent Transactions */}
        <motion.div variants={itemVariants} className="card glass" style={{ marginBottom: '1.25rem' }}>
          <p className="section-title">{t('Recent Transactions', 'हाल के लेनदेन')}</p>
          {txns.length === 0 ? (
            <p style={{ textAlign: 'center', padding: '1rem 0', fontSize: '0.9rem' }}>{t('No transactions yet', 'अभी कोई लेनदेन नहीं')}</p>
          ) : txns.map((tx) => (
            <div key={tx.id} className="list-item">
              <div>
                <div style={{ fontWeight: 600, fontSize: '0.88rem' }}>{tx.description}</div>
                <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: '0.1rem' }}>
                  {new Date(tx.created_at).toLocaleDateString('en-IN')}
                </div>
              </div>
              <div style={{ textAlign: 'right' }}>
                <div style={{ fontWeight: 700, color: tx.type.includes('credit') || tx.type === 'bonus' ? 'var(--success)' : 'var(--danger)', fontSize: '0.95rem' }}>
                  {tx.type.includes('credit') || tx.type === 'bonus' ? '+' : '-'}₹{parseFloat(tx.amount).toFixed(2)}
                </div>
                <div style={{ fontSize: '0.72rem', color: 'var(--text-muted)' }}>Bal: ₹{parseFloat(tx.balance_after).toFixed(2)}</div>
              </div>
            </div>
          ))}
        </motion.div>

      </motion.div>
    </AppLayout>
  );
}
