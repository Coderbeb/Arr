'use client';
import { useState } from 'react';
import { useRouter } from 'next/navigation';
import axios from 'axios';
import { useLanguage } from '@/contexts/LanguageContext';
import { motion, AnimatePresence } from 'framer-motion';
import { Eye, EyeOff } from 'lucide-react';

const API = process.env.NEXT_PUBLIC_API_URL || '/api';

export default function AuthPage() {
  const router = useRouter();
  const { lang, setLang, t } = useLanguage();
  const [mode, setMode] = useState<'login' | 'register' | 'forgot'>('login');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [showPassword, setShowPassword] = useState(false);

  const [form, setForm] = useState({
    mobile_number: '', password: '', full_name: '',
    date_of_birth: '', email: '', city: '', new_password: ''
  });

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setForm(prev => ({ ...prev, [e.target.name]: e.target.value }));
    setError('');
  };

  async function handleLogin(e: React.FormEvent) {
    e.preventDefault(); setLoading(true); setError('');
    try {
      const trimmedMobile = form.mobile_number.trim();
      const res = await axios.post(`${API}/auth/login`, { mobile_number: trimmedMobile, password: form.password });
      localStorage.setItem('token', res.data.token);
      localStorage.setItem('user', JSON.stringify(res.data.user));
      localStorage.setItem('user_id', res.data.user.id);
      localStorage.setItem('role', res.data.user.role);
      setLang(res.data.user.language || 'en');
      if (res.data.user.role === 'super_admin') router.push('/admin');
      else if (res.data.user.role === 'assistance') router.push('/assistance');
      else router.push('/dashboard');
    } catch (err: any) {
      if (err.response?.data?.error) {
        setError(err.response.data.error);
      } else {
        setError(`Login failed: ${err.message || String(err)}`);
      }
    } finally { setLoading(false); }
  }

  async function handleRegister(e: React.FormEvent) {
    e.preventDefault(); setLoading(true); setError('');
    try {
      const res = await axios.post(`${API}/auth/register`, { ...form, language: lang });
      localStorage.setItem('token', res.data.token);
      localStorage.setItem('user', JSON.stringify(res.data.user));
      localStorage.setItem('user_id', res.data.user.id);
      localStorage.setItem('role', res.data.user.role);
      router.push('/dashboard');
    } catch (err: any) {
      setError(err.response?.data?.error || 'Registration failed');
    } finally { setLoading(false); }
  }

  async function handleForgot(e: React.FormEvent) {
    e.preventDefault(); setLoading(true); setError('');
    try {
      await axios.post(`${API}/auth/forgot-password`, {
        mobile_number: form.mobile_number,
        date_of_birth: form.date_of_birth,
        new_password: form.new_password,
      });
      setSuccess(t('Password reset successful! Please login.', 'पासवर्ड रीसेट सफल! कृपया लॉगिन करें।'));
      setMode('login');
    } catch (err: any) {
      setError(err.response?.data?.error || 'Reset failed');
    } finally { setLoading(false); }
  }

  return (
    <div className="auth-page">
      <div className="bg-orb-1"></div>
      <div className="bg-orb-2"></div>

      <div style={{ position: 'fixed', top: '1.5rem', right: '1.5rem', display: 'flex', gap: '0.5rem', zIndex: 100 }}>
        <button className={`btn btn-sm ${lang === 'en' ? 'btn-primary' : 'btn-ghost glass'}`} onClick={() => setLang('en')}>EN</button>
        <button className={`btn btn-sm ${lang === 'hi' ? 'btn-primary' : 'btn-ghost glass'}`} onClick={() => setLang('hi')}>हि</button>
      </div>

      <div className="auth-split-left">
        <motion.div initial={{ opacity: 0, x: -50 }} animate={{ opacity: 1, x: 0 }} transition={{ duration: 0.8 }}>
          <h1>{t('Trade Together.\nEarn Together.', 'साथ व्यापार करें।\nसाथ कमाएं।')}</h1>
          <p>{t('Join the fastest growing peer-to-peer crypto wallet. Secure escrow, instant matching, and premium rewards.', 'सबसे तेज़ी से बढ़ते पीयर-टू-पीयर क्रिप्टो वॉलेट से जुड़ें। सुरक्षित एस्क्रो, त्वरित मैचिंग और प्रीमियम पुरस्कार।')}</p>
        </motion.div>
      </div>

      <div className="auth-split-right">
        <motion.div 
          layout
          initial={{ opacity: 1, scale: 0.95 }} 
          animate={{ opacity: 1, scale: 1 }} 
          transition={{ duration: 0.5, layout: { type: "spring", bounce: 0.2, duration: 0.6 } }}
          className="auth-card glass"
        >
          <div className="auth-logo">
            <div className="auth-logo-text">🪙 Arr Wallet</div>
            <div className="auth-logo-sub">{t('Trade Together. Earn Together.', 'साथ व्यापार करें। साथ कमाएं।')}</div>
          </div>

          {mode !== 'forgot' && (
            <div style={{ display: 'flex', gap: '0.5rem', marginBottom: '1.5rem' }}>
              <button
                className={`btn btn-sm ${mode === 'login' ? 'btn-primary' : 'btn-ghost'}`}
                style={{ flex: 1 }} onClick={() => { setMode('login'); setError(''); }}>
                {t('Login', 'लॉगिन')}
              </button>
              <button
                className={`btn btn-sm ${mode === 'register' ? 'btn-primary' : 'btn-ghost'}`}
                style={{ flex: 1 }} onClick={() => { setMode('register'); setError(''); }}>
                {t('Register', 'रजिस्टर')}
              </button>
            </div>
          )}

          <AnimatePresence mode="wait">
            {error && (
              <motion.div initial={{ opacity: 0, y: -10 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0 }} style={{ background: 'rgba(239,68,68,0.1)', border: '1px solid rgba(239,68,68,0.3)', borderRadius: '8px', padding: '0.75rem', marginBottom: '1rem', color: '#ef4444', fontSize: '0.9rem' }}>
                {error}
              </motion.div>
            )}
            {success && (
              <motion.div initial={{ opacity: 0, y: -10 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0 }} style={{ background: 'rgba(34,197,94,0.1)', border: '1px solid rgba(34,197,94,0.3)', borderRadius: '8px', padding: '0.75rem', marginBottom: '1rem', color: '#22c55e', fontSize: '0.9rem' }}>
                {success}
              </motion.div>
            )}
          </AnimatePresence>

          <AnimatePresence mode="wait">
            {mode === 'login' && (
              <motion.form key="login" initial={{ opacity: 1, x: 0 }} animate={{ opacity: 1, x: 0 }} exit={{ opacity: 0, x: -20 }} onSubmit={handleLogin}>
                <div className="input-group">
                  <label className="input-label">{t('Mobile Number', 'मोबाइल नंबर')}</label>
                  <input className="input" name="mobile_number" type="tel" maxLength={10}
                    placeholder={t('10-digit mobile number', '10 अंकों का मोबाइल नंबर')}
                    value={form.mobile_number} onChange={handleChange} required />
                </div>
                <div className="input-group">
                  <label className="input-label">{t('Password', 'पासवर्ड')}</label>
                  <div style={{ position: 'relative' }}>
                    <input className="input" name="password" type={showPassword ? "text" : "password"}
                      placeholder="••••••••" value={form.password} onChange={handleChange} required 
                      style={{ paddingRight: '2.5rem' }}/>
                    <button 
                      type="button" 
                      onClick={() => setShowPassword(!showPassword)}
                      style={{ position: 'absolute', right: '0.75rem', top: '50%', transform: 'translateY(-50%)', background: 'none', border: 'none', cursor: 'pointer', color: 'var(--text-muted)' }}
                    >
                      {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                    </button>
                  </div>
                </div>
                <button type="submit" className="btn btn-primary btn-full btn-lg" disabled={loading}>
                  {loading ? <span className="spinner" /> : t('Login', 'लॉगिन')}
                </button>
                <button type="button" className="btn btn-ghost btn-full" style={{ marginTop: '0.75rem' }}
                  onClick={() => { setMode('forgot'); setError(''); }}>
                  {t('Forgot Password?', 'पासवर्ड भूल गए?')}
                </button>
              </motion.form>
            )}

            {mode === 'register' && (
              <motion.form key="register" initial={{ opacity: 1, x: 0 }} animate={{ opacity: 1, x: 0 }} exit={{ opacity: 0, x: -20 }} onSubmit={handleRegister}>
                <div className="input-group">
                  <label className="input-label">{t('Full Name', 'पूरा नाम')}</label>
                  <input className="input" name="full_name" placeholder={t('Your full name', 'आपका पूरा नाम')}
                    value={form.full_name} onChange={handleChange} required />
                </div>
                <div className="input-group">
                  <label className="input-label">{t('Mobile Number', 'मोबाइल नंबर')}</label>
                  <input className="input" name="mobile_number" type="tel" maxLength={10}
                    placeholder={t('10-digit mobile number', '10 अंकों का मोबाइल नंबर')}
                    value={form.mobile_number} onChange={handleChange} required />
                </div>
                <div className="input-group">
                  <label className="input-label">{t('Email (Optional)', 'ईमेल (वैकल्पिक)')}</label>
                  <input className="input" name="email" type="email" placeholder="email@example.com"
                    value={form.email} onChange={handleChange} />
                </div>
                <div className="input-group">
                  <label className="input-label">{t('City', 'शहर')}</label>
                  <input className="input" name="city" placeholder={t('Your city', 'आपका शहर')}
                    value={form.city} onChange={handleChange} required />
                </div>
                <div className="input-group">
                  <label className="input-label">{t('Date of Birth', 'जन्म तिथि')}</label>
                  <input className="input" name="date_of_birth" type="date"
                    value={form.date_of_birth} onChange={handleChange} required />
                </div>
                <div className="input-group">
                  <label className="input-label">{t('Password', 'पासवर्ड')}</label>
                  <div style={{ position: 'relative' }}>
                    <input className="input" name="password" type={showPassword ? "text" : "password"}
                      placeholder="Min 8 characters" value={form.password} onChange={handleChange} required minLength={8} 
                      style={{ paddingRight: '2.5rem' }}/>
                    <button 
                      type="button" 
                      onClick={() => setShowPassword(!showPassword)}
                      style={{ position: 'absolute', right: '0.75rem', top: '50%', transform: 'translateY(-50%)', background: 'none', border: 'none', cursor: 'pointer', color: 'var(--text-muted)' }}
                    >
                      {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                    </button>
                  </div>
                </div>
                <button type="submit" className="btn btn-primary btn-full btn-lg" disabled={loading}>
                  {loading ? <span className="spinner" /> : t('Create Account', 'खाता बनाएं')}
                </button>
              </motion.form>
            )}

            {mode === 'forgot' && (
              <motion.form key="forgot" initial={{ opacity: 1, x: 0 }} animate={{ opacity: 1, x: 0 }} exit={{ opacity: 0, x: -20 }} onSubmit={handleForgot}>
                <div style={{ marginBottom: '1.5rem' }}>
                  <button type="button" className="btn btn-ghost btn-sm" onClick={() => setMode('login')}>← {t('Back', 'वापस')}</button>
                  <h3 style={{ marginTop: '1rem' }}>{t('Reset Password', 'पासवर्ड रीसेट')}</h3>
                  <p style={{ fontSize: '0.85rem', marginTop: '0.25rem' }}>{t('Enter your DOB to verify identity', 'पहचान सत्यापित करने के लिए जन्म तिथि दर्ज करें')}</p>
                </div>
                <div className="input-group">
                  <label className="input-label">{t('Mobile Number', 'मोबाइल नंबर')}</label>
                  <input className="input" name="mobile_number" type="tel"
                    value={form.mobile_number} onChange={handleChange} required />
                </div>
                <div className="input-group">
                  <label className="input-label">{t('Date of Birth', 'जन्म तिथि')}</label>
                  <input className="input" name="date_of_birth" type="date"
                    value={form.date_of_birth} onChange={handleChange} required />
                </div>
                <div className="input-group">
                  <label className="input-label">{t('New Password', 'नया पासवर्ड')}</label>
                  <div style={{ position: 'relative' }}>
                    <input className="input" name="new_password" type={showPassword ? "text" : "password"}
                      placeholder="Min 8 characters" value={form.new_password} onChange={handleChange} required minLength={8} 
                      style={{ paddingRight: '2.5rem' }}/>
                    <button 
                      type="button" 
                      onClick={() => setShowPassword(!showPassword)}
                      style={{ position: 'absolute', right: '0.75rem', top: '50%', transform: 'translateY(-50%)', background: 'none', border: 'none', cursor: 'pointer', color: 'var(--text-muted)' }}
                    >
                      {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                    </button>
                  </div>
                </div>
                <button type="submit" className="btn btn-primary btn-full btn-lg" disabled={loading} style={{ marginTop: '1rem' }}>
                  {loading ? <span className="spinner" /> : t('Reset Password', 'पासवर्ड रीसेट करें')}
                </button>
              </motion.form>
            )}
          </AnimatePresence>
        </motion.div>
      </div>
    </div>
  );
}
