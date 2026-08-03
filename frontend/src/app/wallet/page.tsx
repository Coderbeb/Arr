'use client';
import { useState, useEffect, useCallback } from 'react';
import { useRouter } from 'next/navigation';
import axios from 'axios';
import AppLayout from '@/components/AppLayout';
import { useLanguage } from '@/contexts/LanguageContext';
import { motion } from 'framer-motion';

const API = process.env.NEXT_PUBLIC_API_URL || '/api';

export default function WalletPage() {
  const router = useRouter();
  const { lang, t } = useLanguage();
  const [wallet, setWallet] = useState<any>(null);
  const [txns, setTxns] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const token = typeof window !== 'undefined' ? localStorage.getItem('token') : '';
  const headers = { Authorization: `Bearer ${token}` };

  const fetchData = useCallback(async () => {
    if (!token) { router.push('/'); return; }
    try {
      const [walletRes, txnRes] = await Promise.all([
        axios.get(`${API}/wallet/balance`, { headers }),
        axios.get(`${API}/wallet/transactions?lang=${lang}`, { headers }),
      ]);
      setWallet(walletRes.data);
      setTxns(txnRes.data);
    } catch { router.push('/'); }
    finally { setLoading(false); }
  }, [lang, token, headers]);

  useEffect(() => { fetchData(); }, [fetchData]);

  if (loading) return (
    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', minHeight: '100vh' }}>
      <div className="spinner" style={{ width: 48, height: 48 }} />
    </div>
  );

  const dailyPct = wallet ? Math.min(100, (parseFloat(wallet.daily_earned) / parseFloat(wallet.max_daily_earning)) * 100) : 0;
  const weeklyPct = wallet ? Math.min(100, (parseFloat(wallet.weekly_earned) / parseFloat(wallet.max_weekly_earning)) * 100) : 0;

  const containerVariants = { hidden: { opacity: 0 }, show: { opacity: 1, transition: { staggerChildren: 0.1 } } };
  const itemVariants = { hidden: { opacity: 0, y: 20 }, show: { opacity: 1, y: 0 } };

  return (
    <AppLayout title={t('Wallet', 'वॉलेट')}>
      <motion.div variants={containerVariants} initial="hidden" animate="show" style={{ display: 'grid', gap: '1.25rem', gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))' }}>
        
        {/* Left Column: Balance & Progress */}
        <div>
        {/* Balance */}
        <motion.div variants={itemVariants} className="balance-card card-glow glass" style={{ marginBottom: '1.25rem' }}>
          <div className="balance-label">{t('Available Balance', 'उपलब्ध बैलेंस')}</div>
          <div className="balance-amount">₹{parseFloat(wallet?.wallet_balance || 0).toFixed(2)}</div>
          {parseFloat(wallet?.escrow_balance || 0) > 0 && (
            <div style={{ marginTop: '0.5rem', fontSize: '0.8rem', color: 'var(--warning)' }}>
              🔒 ₹{parseFloat(wallet.escrow_balance).toFixed(2)} {t('locked in escrow', 'एस्क्रो में लॉक')}
            </div>
          )}
        </motion.div>

        {/* Earnings Progress */}
        <motion.div variants={itemVariants} className="card glass" style={{ marginBottom: '1.25rem' }}>
          <p className="section-title">📈 {t('Earnings', 'कमाई')}</p>

          <div style={{ marginBottom: '1rem' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.8rem', color: 'var(--text-muted)', marginBottom: '0.3rem' }}>
              <span>{t("Today's Earnings", 'आज की कमाई')}</span>
              <span>₹{parseFloat(wallet?.daily_earned || 0).toFixed(2)} / ₹{wallet?.max_daily_earning}</span>
            </div>
            <div className="progress-bar"><motion.div className="progress-fill" initial={{ width: 0 }} animate={{ width: `${dailyPct}%` }} transition={{ duration: 1 }} /></div>
          </div>

          <div>
            <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.8rem', color: 'var(--text-muted)', marginBottom: '0.3rem' }}>
              <span>{t('Weekly Earnings', 'साप्ताहिक कमाई')}</span>
              <span>₹{parseFloat(wallet?.weekly_earned || 0).toFixed(2)} / ₹{wallet?.max_weekly_earning}</span>
            </div>
            <div className="progress-bar"><motion.div className="progress-fill" initial={{ width: 0 }} animate={{ width: `${weeklyPct}%` }} transition={{ duration: 1, delay: 0.2 }} /></div>
          </div>
        </motion.div>

        </div>

        {/* Right Column: Transactions */}
        <div>
        {/* Transactions */}
        <motion.div variants={itemVariants} className="card glass">
          <p className="section-title">📝 {t('Transaction History', 'लेनदेन इतिहास')}</p>
          {txns.length === 0 ? (
            <p style={{ textAlign: 'center', padding: '1.5rem', fontSize: '0.9rem' }}>
              {t('No transactions yet', 'अभी कोई लेनदेन नहीं')}
            </p>
          ) : txns.map((tx) => {
            const isCredit = tx.type.includes('credit') || tx.type === 'bonus' || tx.type === 'admin_credit';
            return (
              <div key={tx.id} className="list-item">
                <div style={{ flex: 1 }}>
                  <div style={{ fontWeight: 600, fontSize: '0.88rem' }}>
                    {isCredit ? '📥' : '📤'} {tx.description}
                  </div>
                  <div style={{ fontSize: '0.72rem', color: 'var(--text-muted)', marginTop: '0.15rem' }}>
                    {new Date(tx.created_at).toLocaleString('en-IN', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })}
                  </div>
                </div>
                <div style={{ textAlign: 'right' }}>
                  <div style={{ fontWeight: 700, fontSize: '0.95rem', color: isCredit ? 'var(--success)' : 'var(--danger)' }}>
                    {isCredit ? '+' : '-'}₹{parseFloat(tx.amount).toFixed(2)}
                  </div>
                  <div style={{ fontSize: '0.68rem', color: 'var(--text-muted)' }}>
                    {t('Bal:', 'बैल:')} ₹{parseFloat(tx.balance_after).toFixed(2)}
                  </div>
                </div>
              </div>
            );
          })}
        </motion.div>
        </div>
      </motion.div>
    </AppLayout>
  );
}
