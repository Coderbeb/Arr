'use client';
import { useState, useEffect, useCallback } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import axios from 'axios';
import AppLayout from '@/components/AppLayout';
import { getFileUrl } from '@/utils/imageUrl';
import { motion, AnimatePresence } from 'framer-motion';

const API = process.env.NEXT_PUBLIC_API_URL || '/api';

export default function AdminDashboard() {
  const router = useRouter();
  const [stats, setStats] = useState<any>(null);
  const [settings, setSettings] = useState<any>(null);
  const [users, setUsers] = useState<any[]>([]);
  const [amounts, setAmounts] = useState<any[]>([]);
  const [escalated, setEscalated] = useState<any[]>([]);
  const [assistanceManagers, setAssistanceManagers] = useState<any[]>([]);
  
  const searchParams = useSearchParams();
  const tab = searchParams.get('tab') || 'overview';
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  // Modals
  const [creditModal, setCreditModal] = useState<{ userId: string; name: string } | null>(null);
  const [creditAmount, setCreditAmount] = useState('');
  const [creditReason, setCreditReason] = useState('');
  
  const [userDetailsModal, setUserDetailsModal] = useState<any>(null);
  
  const [newManagerForm, setNewManagerForm] = useState({ full_name: '', mobile_number: '', date_of_birth: '', password: '' });

  // Escalated Dispute Resolution State
  const [selectedEscalated, setSelectedEscalated] = useState<any>(null);
  const [decision, setDecision] = useState('');
  const [notes, setNotes] = useState('');
  const [zoomedImage, setZoomedImage] = useState('');

  const token = typeof window !== 'undefined' ? localStorage.getItem('token') : '';
  const headers = { Authorization: `Bearer ${token}` };

  const fetchData = useCallback(async () => {
    if (!token) { router.push('/'); return; }
    try {
      const [statsRes, settingsRes, usersRes, mgrsRes, amountsRes, escalatedRes] = await Promise.all([
        axios.get(`${API}/admin/stats`, { headers }),
        axios.get(`${API}/admin/settings`, { headers }),
        axios.get(`${API}/admin/users`, { headers }),
        axios.get(`${API}/admin/users?role=assistance`, { headers }), // Fetch assistance managers separately
        axios.get(`${API}/admin/amounts`, { headers }),
        axios.get(`${API}/admin/escalated-disputes`, { headers }),
      ]);
      setStats(statsRes.data);
      setSettings(settingsRes.data);
      setUsers(usersRes.data);
      setAssistanceManagers(mgrsRes.data);
      setAmounts(amountsRes.data);
      setEscalated(escalatedRes.data);
    } catch { router.push('/'); }
    finally { setLoading(false); }
  }, []);

  useEffect(() => { fetchData(); }, [fetchData]);

  async function saveSettings() {
    setSaving(true); setError(''); setSuccess('');
    try {
      await axios.put(`${API}/admin/settings`, settings, { headers });
      setSuccess('Settings updated successfully!');
    } catch (err: any) { setError(err.response?.data?.error || 'Failed to update settings'); }
    finally { setSaving(false); }
  }

  async function updateUserStatus(userId: string, status: string) {
    try {
      await axios.put(`${API}/admin/users/${userId}/status`, { status, reason: 'Admin action' }, { headers });
      fetchData();
    } catch {}
  }

  async function creditUser() {
    if (!creditModal) return;
    setSaving(true);
    try {
      await axios.post(`${API}/admin/users/${creditModal.userId}/credit`,
        { amount: parseFloat(creditAmount), reason: creditReason }, { headers });
      setSuccess(`₹${creditAmount} credited to ${creditModal.name}`);
      setCreditModal(null); setCreditAmount(''); setCreditReason('');
      fetchData();
    } catch (err: any) { setError(err.response?.data?.error || 'Failed'); }
    finally { setSaving(false); }
  }

  async function fetchUserDetails(userId: string) {
    try {
      const res = await axios.get(`${API}/admin/users/${userId}/details`, { headers });
      setUserDetailsModal(res.data);
    } catch (err: any) {
      setError('Could not fetch user details.');
    }
  }

  async function deleteAmount(id: string) {
    if (!confirm('Are you sure you want to delete this amount?')) return;
    try {
      await axios.delete(`${API}/admin/amounts/${id}`, { headers });
      fetchData();
    } catch (err: any) { setError('Failed to delete amount'); }
  }

  async function addAmount(e: React.FormEvent) {
    e.preventDefault();
    const amountStr = (e.target as any).amount.value;
    try {
      await axios.post(`${API}/admin/amounts`, { amount: parseFloat(amountStr), sort_order: amounts.length + 1 }, { headers });
      (e.target as any).reset();
      fetchData();
    } catch (err: any) { setError(err.response?.data?.error || 'Failed to add amount'); }
  }

  async function createManager(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    try {
      await axios.post(`${API}/admin/create-manager`, newManagerForm, { headers });
      setSuccess('Assistance Manager created!');
      setNewManagerForm({ full_name: '', mobile_number: '', date_of_birth: '', password: '' });
      fetchData();
    } catch (err: any) { setError(err.response?.data?.error || 'Failed to create manager'); }
    finally { setSaving(false); }
  }

  async function resolveEscalatedDispute() {
    if (!selectedEscalated || !decision || notes.length < 10) return;
    setSaving(true); setError('');
    try {
      await axios.post(`${API}/admin/escalated-disputes/${selectedEscalated.id}/resolve`, { decision, notes }, { headers });
      setSuccess('Escalated dispute resolved successfully!');
      setSelectedEscalated(null); setDecision(''); setNotes('');
      fetchData();
    } catch (err: any) { setError(err.response?.data?.error || 'Failed to resolve dispute'); }
    finally { setSaving(false); }
  }

  const scoreColor = (score: number | null) => {
    if (score == null) return 'var(--text-muted)';
    if (score >= 70) return '#22c55e';
    if (score >= 40) return '#f59e0b';
    return '#ef4444';
  };

  if (loading) {
    return (
    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', minHeight: '100vh' }}>
      <div className="spinner" style={{ width: 48, height: 48, borderColor: 'var(--gold) transparent var(--gold) transparent' }} />
    </div>
  );
  }

  return (
    <AppLayout role="admin" title="Super Admin">
      <div className="page-inner">
        {/* ZOOM MODAL */}
        <AnimatePresence>
          {zoomedImage && (
            <motion.div
              initial={{ opacity: 0, scale: 0.9 }} animate={{ opacity: 1, scale: 1 }} exit={{ opacity: 0, scale: 0.9 }}
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

        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '2rem' }}>
          <h1>👑 Super Admin Dashboard</h1>
        </div>
        
        {/* Alerts */}
        {error && <div style={{ background: 'rgba(239,68,68,0.1)', border: '1px solid rgba(239,68,68,0.3)', borderRadius: 8, padding: '1rem', marginBottom: '1.5rem', color: '#ef4444', fontWeight: 500 }}>{error}</div>}
        {success && <div style={{ background: 'rgba(34,197,94,0.1)', border: '1px solid rgba(34,197,94,0.3)', borderRadius: 8, padding: '1rem', marginBottom: '1.5rem', color: '#22c55e', fontWeight: 500 }}>{success}</div>}

        {/* OVERVIEW TAB */}
        {tab === 'overview' && stats && (
          <div className="fade-in">
            <h2 style={{ marginBottom: '1.5rem', fontSize: '1.5rem' }}>Platform Analytics</h2>
            
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '1rem', marginBottom: '2rem' }}>
              <div className="card" style={{ borderLeft: '4px solid #3b82f6', textAlign: 'center' }}>
                <div style={{ fontSize: '2rem', marginBottom: '0.5rem' }}>🟢</div>
                <div style={{ fontSize: '1.8rem', fontWeight: 800 }}>{stats.active_online_users}</div>
                <div style={{ color: 'var(--text-muted)' }}>Active Users Online</div>
              </div>
              <div className="card" style={{ borderLeft: '4px solid var(--gold)', textAlign: 'center' }}>
                <div style={{ fontSize: '2rem', marginBottom: '0.5rem' }}>👥</div>
                <div style={{ fontSize: '1.8rem', fontWeight: 800 }}>{stats.users?.total || 0}</div>
                <div style={{ color: 'var(--text-muted)' }}>Total Registrations</div>
              </div>
              <div className="card" style={{ borderLeft: '4px solid #22c55e', textAlign: 'center' }}>
                <div style={{ fontSize: '2rem', marginBottom: '0.5rem' }}>💰</div>
                <div style={{ fontSize: '1.8rem', fontWeight: 800 }}>₹{stats.revenue_today || 0}</div>
                <div style={{ color: 'var(--text-muted)' }}>Revenue Today</div>
              </div>
              <div className="card" style={{ borderLeft: '4px solid #a855f7', textAlign: 'center' }}>
                <div style={{ fontSize: '2rem', marginBottom: '0.5rem' }}>📊</div>
                <div style={{ fontSize: '1.8rem', fontWeight: 800 }}>{stats.trades_today || 0}</div>
                <div style={{ color: 'var(--text-muted)' }}>Trades Today</div>
              </div>
              <div className="card" style={{ borderLeft: '4px solid #ef4444', textAlign: 'center' }}>
                <div style={{ fontSize: '2rem', marginBottom: '0.5rem' }}>🚨</div>
                <div style={{ fontSize: '1.8rem', fontWeight: 800 }}>{stats.fraud_cases_today || 0}</div>
                <div style={{ color: 'var(--text-muted)' }}>Fraud / Disputes Today</div>
              </div>
            </div>

            <div className="card">
              <h3 style={{ marginBottom: '1rem', borderBottom: '1px solid var(--border)', paddingBottom: '0.5rem' }}>📍 Top Regions (City)</h3>
              {stats.regional_stats?.length > 0 ? (
                <ul style={{ listStyle: 'none', padding: 0 }}>
                  {stats.regional_stats.map((r: any, idx: number) => (
                    <li key={idx} style={{ display: 'flex', justifyContent: 'space-between', padding: '0.75rem 0', borderBottom: '1px solid rgba(255,255,255,0.05)' }}>
                      <span style={{ fontWeight: 500 }}>{r.city}</span>
                      <span className="badge badge-primary">{r.count} users</span>
                    </li>
                  ))}
                </ul>
              ) : <p style={{ color: 'var(--text-muted)' }}>No regional data available yet.</p>}
            </div>
          </div>
        )}

        {/* SETTINGS TAB */}
        {tab === 'settings' && settings && (
          <div className="card fade-in" style={{ maxWidth: '800px', margin: '0 auto' }}>
            <h2 style={{ marginBottom: '1.5rem', color: 'var(--gold)' }}>⚙️ Platform Settings</h2>
            
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))', gap: '1.5rem' }}>
              <div className="input-group">
                <label className="input-label" style={{ display: 'flex', justifyContent: 'space-between' }}>
                  Commission % 
                  <span style={{ color: 'var(--text-muted)', fontWeight: 400 }}>(e.g. 8 for 8%)</span>
                </label>
                <input className="input" type="number" step="0.1" value={settings.commission_percent ?? ''}
                  onChange={(e) => setSettings({ ...settings, commission_percent: e.target.value })} />
              </div>
              
              <div className="input-group">
                <label className="input-label">Max Daily Earning (₹)</label>
                <input className="input" type="number" value={settings.max_daily_earning ?? ''}
                  onChange={(e) => setSettings({ ...settings, max_daily_earning: e.target.value })} />
              </div>

              <div className="input-group">
                <label className="input-label">Max Weekly Earning (₹)</label>
                <input className="input" type="number" value={settings.max_weekly_earning ?? ''}
                  onChange={(e) => setSettings({ ...settings, max_weekly_earning: e.target.value })} />
              </div>

              <div className="input-group">
                <label className="input-label">Trade Accept Timer (min)</label>
                <input className="input" type="number" value={settings.trade_accept_minutes ?? ''}
                  onChange={(e) => setSettings({ ...settings, trade_accept_minutes: e.target.value })} />
              </div>

              <div className="input-group">
                <label className="input-label">Payment Timer (min)</label>
                <input className="input" type="number" value={settings.payment_timer_minutes ?? ''}
                  onChange={(e) => setSettings({ ...settings, payment_timer_minutes: e.target.value })} />
              </div>

              <div className="input-group">
                <label className="input-label">Dispute Proof Timer (min)</label>
                <input className="input" type="number" value={settings.dispute_proof_minutes ?? ''}
                  onChange={(e) => setSettings({ ...settings, dispute_proof_minutes: e.target.value })} />
              </div>
            </div>

            <div className="input-group" style={{ marginTop: '1.5rem', background: 'rgba(255,255,255,0.02)', padding: '1rem', borderRadius: '8px' }}>
              <label className="input-label" style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', margin: 0, cursor: 'pointer' }}>
                <input type="checkbox" checked={settings.registration_open}
                  onChange={(e) => setSettings({ ...settings, registration_open: e.target.checked })}
                  style={{ width: 24, height: 24, accentColor: 'var(--gold)' }} />
                <span style={{ fontSize: '1.1rem' }}>User Registration Open</span>
              </label>
              <p style={{ margin: '0.5rem 0 0 2.2rem', color: 'var(--text-muted)', fontSize: '0.85rem' }}>Uncheck this to pause new sign-ups globally.</p>
            </div>
            
            <button className="btn btn-primary btn-full" style={{ marginTop: '2rem', height: '3.5rem', fontSize: '1.1rem' }} onClick={saveSettings} disabled={saving}>
              {saving ? <span className="spinner" /> : 'Save All Settings'}
            </button>
          </div>
        )}

        {/* USERS TAB */}
        {tab === 'users' && (
          <div className="card fade-in">
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1.5rem', flexWrap: 'wrap', gap: '1rem' }}>
              <h2 style={{ margin: 0 }}>👥 Users Directory</h2>
            </div>
            
            <div style={{ overflowX: 'auto' }}>
              <table style={{ width: '100%', textAlign: 'left', borderCollapse: 'collapse' }}>
                <thead>
                  <tr style={{ borderBottom: '1px solid var(--border)', color: 'var(--text-muted)' }}>
                    <th style={{ padding: '1rem' }}>Name / Mobile</th>
                    <th style={{ padding: '1rem' }}>City</th>
                    <th style={{ padding: '1rem' }}>Wallet / Trades</th>
                    <th style={{ padding: '1rem' }}>Status</th>
                    <th style={{ padding: '1rem' }}>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {users.map((u) => (
                    <tr key={u.id} style={{ borderBottom: '1px solid rgba(255,255,255,0.05)' }}>
                      <td style={{ padding: '1rem' }}>
                        <div style={{ fontWeight: 600 }}>{u.full_name}</div>
                        <div style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>{u.mobile_number}</div>
                      </td>
                      <td style={{ padding: '1rem', color: 'var(--text-muted)' }}>{u.city || 'N/A'}</td>
                      <td style={{ padding: '1rem' }}>
                        <div style={{ fontWeight: 600 }}>₹{u.wallet_balance}</div>
                        <div style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>{u.total_trades} trades</div>
                      </td>
                      <td style={{ padding: '1rem' }}>
                        <span className={`badge ${u.status === 'active' ? 'badge-success' : u.status === 'suspended' ? 'badge-warning' : 'badge-danger'}`}>{u.status}</span>
                      </td>
                      <td style={{ padding: '1rem' }}>
                        <div style={{ display: 'flex', gap: '0.5rem' }}>
                          <button className="btn btn-sm btn-ghost" onClick={() => fetchUserDetails(u.id)}>👁️ Details</button>
                          <button className="btn btn-sm btn-ghost" onClick={() => setCreditModal({ userId: u.id, name: u.full_name })}>💰 Credit</button>
                          {u.status === 'active' && <button className="btn btn-sm btn-danger" onClick={() => updateUserStatus(u.id, 'suspended')}>Suspend</button>}
                          {u.status === 'suspended' && <button className="btn btn-sm btn-success" onClick={() => updateUserStatus(u.id, 'active')}>Activate</button>}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}

        {/* AMOUNTS TAB */}
        {tab === 'amounts' && (
          <div className="card fade-in" style={{ maxWidth: '600px', margin: '0 auto' }}>
            <h2 style={{ marginBottom: '1.5rem' }}>💰 Trade Amounts Configuration</h2>
            
            <form onSubmit={addAmount} style={{ display: 'flex', gap: '0.5rem', marginBottom: '2rem' }}>
              <input type="number" name="amount" className="input" placeholder="Enter amount (e.g. 1500)" min={1000} max={2000} required style={{ flex: 1 }} />
              <button type="submit" className="btn btn-primary">Add Amount</button>
            </form>

            <ul style={{ listStyle: 'none', padding: 0 }}>
              {amounts.map((a) => (
                <li key={a.id} className="list-item" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                  <span style={{ fontSize: '1.2rem', fontWeight: 600 }}>₹{parseFloat(a.amount).toLocaleString()}</span>
                  <div style={{ display: 'flex', gap: '0.5rem' }}>
                    <span className={`badge ${a.is_active ? 'badge-success' : 'badge-danger'}`}>{a.is_active ? 'Active' : 'Inactive'}</span>
                    <button className="btn btn-sm btn-danger" onClick={() => deleteAmount(a.id)}>Delete</button>
                  </div>
                </li>
              ))}
            </ul>
          </div>
        )}

        {/* ASSISTANCE TAB */}
        {tab === 'assistance' && (
          <div className="fade-in">
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1.5rem' }}>
              <div className="card">
                <h2 style={{ marginBottom: '1.5rem' }}>👨‍💼 Assistance Managers</h2>
                {assistanceManagers.length === 0 ? <p style={{ color: 'var(--text-muted)' }}>No managers found.</p> : (
                  <ul style={{ listStyle: 'none', padding: 0 }}>
                    {assistanceManagers.map(m => (
                      <li key={m.id} className="list-item" style={{ flexDirection: 'column', alignItems: 'flex-start' }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', width: '100%' }}>
                          <span style={{ fontWeight: 600, fontSize: '1.1rem' }}>{m.full_name}</span>
                          <span className={`badge ${m.status === 'active' ? 'badge-success' : 'badge-danger'}`}>{m.status}</span>
                        </div>
                        <div style={{ color: 'var(--text-muted)', fontSize: '0.9rem', marginBottom: '0.5rem' }}>📱 {m.mobile_number}</div>
                        <div style={{ display: 'flex', gap: '0.5rem', marginTop: '0.5rem' }}>
                          <button className="btn btn-sm btn-ghost" onClick={() => setCreditModal({ userId: m.id, name: m.full_name })}>💰 Credit</button>
                          {m.status === 'active' ? 
                            <button className="btn btn-sm btn-danger" onClick={() => updateUserStatus(m.id, 'suspended')}>Suspend</button> :
                            <button className="btn btn-sm btn-success" onClick={() => updateUserStatus(m.id, 'active')}>Activate</button>
                          }
                        </div>
                      </li>
                    ))}
                  </ul>
                )}
              </div>

              <div className="card">
                <h2 style={{ marginBottom: '1.5rem' }}>➕ Create New Manager</h2>
                <form onSubmit={createManager}>
                  <div className="input-group">
                    <label className="input-label">Full Name</label>
                    <input className="input" value={newManagerForm.full_name} onChange={e => setNewManagerForm({...newManagerForm, full_name: e.target.value})} required />
                  </div>
                  <div className="input-group">
                    <label className="input-label">Mobile Number</label>
                    <input className="input" type="tel" maxLength={10} value={newManagerForm.mobile_number} onChange={e => setNewManagerForm({...newManagerForm, mobile_number: e.target.value})} required />
                  </div>
                  <div className="input-group">
                    <label className="input-label">Date of Birth</label>
                    <input className="input" type="date" value={newManagerForm.date_of_birth} onChange={e => setNewManagerForm({...newManagerForm, date_of_birth: e.target.value})} required />
                  </div>
                  <div className="input-group">
                    <label className="input-label">Secure Password</label>
                    <input className="input" type="password" value={newManagerForm.password} onChange={e => setNewManagerForm({...newManagerForm, password: e.target.value})} required />
                  </div>
                  <button type="submit" className="btn btn-primary btn-full" disabled={saving}>
                    {saving ? <span className="spinner" /> : 'Create Account'}
                  </button>
                </form>
              </div>
            </div>
          </div>
        )}

        {/* ESCALATED DISPUTES */}
        {tab === 'escalated' && !selectedEscalated && (
          <div className="card fade-in">
            <h2 style={{ marginBottom: '1.5rem', color: '#ef4444' }}>🚨 Escalated Disputes ({escalated.length})</h2>
            {escalated.length === 0 ? (
              <p style={{ textAlign: 'center', padding: '2rem', fontSize: '1.1rem', color: 'var(--text-muted)' }}>No escalated disputes requiring your attention.</p>
            ) : escalated.map((d) => (
              <div key={d.id} className="list-item" style={{ flexDirection: 'column', alignItems: 'flex-start', gap: '0.5rem', borderLeft: '4px solid #ef4444' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', width: '100%' }}>
                  <span style={{ fontWeight: 700, fontSize: '1.2rem' }}>₹{parseFloat(d.amount).toLocaleString()}</span>
                  <span className="badge badge-danger">Escalated by {d.escalated_by_name}</span>
                </div>
                <div style={{ fontSize: '0.95rem', color: 'var(--text-muted)' }}>
                  <strong>Buyer:</strong> {d.buyer_name} <br/>
                  <strong>Seller:</strong> {d.seller_name} <br/>
                  <strong>UTR:</strong> {d.utr_number}
                </div>
                <div style={{ background: 'rgba(255,255,255,0.05)', padding: '0.75rem', borderRadius: 8, marginTop: '0.5rem', fontStyle: 'italic', fontSize: '0.9rem' }}>
                  "{d.resolution_notes}"
                </div>
                <button
                  className="btn btn-primary btn-sm"
                  style={{ width: '100%', marginTop: '0.5rem', fontWeight: 600 }}
                  onClick={() => setSelectedEscalated(d)}
                >
                  🔍 Review & Resolve →
                </button>
              </div>
            ))}
          </div>
        )}

        {/* ESCALATED DISPUTE DETAIL */}
        {tab === 'escalated' && selectedEscalated && (
          <div className="card glass fade-in">
            <button className="btn btn-sm btn-ghost" onClick={() => setSelectedEscalated(null)} style={{ marginBottom: '1rem' }}>
              ← Back
            </button>

            <h3 style={{ marginBottom: '0.75rem' }}>
              Escalated Dispute Detail — ₹{parseFloat(selectedEscalated.amount).toLocaleString()}
            </h3>
            <div style={{ fontSize: '0.85rem', marginBottom: '0.75rem', color: 'var(--text-muted)' }}>
              UTR: <strong style={{ color: 'var(--gold)' }}>{selectedEscalated.utr_number}</strong>
            </div>

            <div style={{ background: 'rgba(239,68,68,0.1)', border: '1px solid rgba(239,68,68,0.3)', borderRadius: 8, padding: '1rem', marginBottom: '1.25rem' }}>
              <div style={{ fontSize: '0.8rem', color: '#ef4444', fontWeight: 600, marginBottom: '0.25rem' }}>
                Escalated by Assistance Manager: {selectedEscalated.escalated_by_name}
              </div>
              <div style={{ fontStyle: 'italic', fontSize: '0.9rem', color: 'var(--text)' }}>
                "{selectedEscalated.resolution_notes}"
              </div>
            </div>

            {/* Side-by-Side Proof Comparison */}
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.75rem', marginBottom: '1.25rem' }}>
              {/* BUYER SIDE */}
              <div className="card" style={{ borderColor: 'rgba(59,130,246,0.3)', padding: '0.75rem' }}>
                <p className="section-title" style={{ color: '#3b82f6', fontSize: '0.85rem' }}>
                  🛒 Buyer: {selectedEscalated.buyer_name}
                </p>
                {/* Proof Files */}
                <div style={{ marginTop: '0.75rem' }}>
                  <div style={{ fontSize: '0.7rem', color: 'var(--text-muted)', marginBottom: '0.3rem' }}>Evidence</div>
                  {selectedEscalated.buyer_screen_recording_url && (
                    <div style={{ marginBottom: '0.5rem' }}>
                      <div style={{ fontSize: '0.7rem', marginBottom: '0.2rem' }}>🎥 Screen Recording</div>
                      <video
                        src={getFileUrl(selectedEscalated.buyer_screen_recording_url)} controls playsInline preload="metadata"
                        style={{ width: '100%', maxHeight: 260, borderRadius: 8, background: '#000' }}
                      />
                    </div>
                  )}
                  {selectedEscalated.buyer_txn_screenshot_url && (
                    <div style={{ marginBottom: '0.5rem' }}>
                      <div style={{ fontSize: '0.7rem', marginBottom: '0.2rem' }}>📸 Transaction Screenshot</div>
                      <img src={getFileUrl(selectedEscalated.buyer_txn_screenshot_url)} alt="Buyer txn"
                        onClick={() => setZoomedImage(selectedEscalated.buyer_txn_screenshot_url)}
                        style={{ width: '100%', maxHeight: 120, objectFit: 'cover', borderRadius: 6, cursor: 'zoom-in' }} />
                    </div>
                  )}
                  {selectedEscalated.buyer_proof_submitted_at
                    ? <div className="badge badge-success" style={{ marginTop: '0.5rem' }}>Proof uploaded</div>
                    : <div className="badge badge-danger" style={{ marginTop: '0.5rem' }}>No proof</div>
                  }
                </div>
              </div>

              {/* SELLER SIDE */}
              <div className="card" style={{ borderColor: 'rgba(245,158,11,0.3)', padding: '0.75rem' }}>
                <p className="section-title" style={{ color: '#f59e0b', fontSize: '0.85rem' }}>
                  🏪 Seller: {selectedEscalated.seller_name}
                </p>
                {/* Proof Files */}
                <div style={{ marginTop: '0.75rem' }}>
                  <div style={{ fontSize: '0.7rem', color: 'var(--text-muted)', marginBottom: '0.3rem' }}>Evidence</div>
                  {selectedEscalated.seller_screen_recording_url && (
                    <div style={{ marginBottom: '0.5rem' }}>
                      <div style={{ fontSize: '0.7rem', marginBottom: '0.2rem' }}>🎥 Screen Recording</div>
                      <video
                        src={getFileUrl(selectedEscalated.seller_screen_recording_url)} controls playsInline preload="metadata"
                        style={{ width: '100%', maxHeight: 260, borderRadius: 8, background: '#000' }}
                      />
                    </div>
                  )}
                  {selectedEscalated.seller_txn_screenshot_url && (
                    <div style={{ marginBottom: '0.5rem' }}>
                      <div style={{ fontSize: '0.7rem', marginBottom: '0.2rem' }}>📸 Transaction Screenshot</div>
                      <img src={getFileUrl(selectedEscalated.seller_txn_screenshot_url)} alt="Seller txn"
                        onClick={() => setZoomedImage(selectedEscalated.seller_txn_screenshot_url)}
                        style={{ width: '100%', maxHeight: 120, objectFit: 'cover', borderRadius: 6, cursor: 'zoom-in' }} />
                    </div>
                  )}
                  {selectedEscalated.seller_proof_submitted_at
                    ? <div className="badge badge-success" style={{ marginTop: '0.5rem' }}>Proof uploaded</div>
                    : <div className="badge badge-danger" style={{ marginTop: '0.5rem' }}>No proof</div>
                  }
                </div>
              </div>
            </div>

            <hr className="divider" />

            {/* Resolution */}
            <p className="section-title">⚖️ Final Resolution</p>
            <div style={{ display: 'flex', gap: '0.5rem', marginBottom: '1rem' }}>
              <button className={`btn btn-sm ${decision === 'buyer' ? 'btn-primary' : 'btn-secondary'}`} onClick={() => setDecision('buyer')}>
                ✅ Buyer Wins (Refund)
              </button>
              <button className={`btn btn-sm ${decision === 'seller' ? 'btn-primary' : 'btn-secondary'}`} onClick={() => setDecision('seller')}>
                ✅ Seller Wins (Release)
              </button>
            </div>
            <div className="input-group">
              <label className="input-label">Super Admin Notes (min 10 chars)</label>
              <textarea className="input" style={{ minHeight: 80, resize: 'vertical' }}
                value={notes} onChange={(e) => setNotes(e.target.value)}
                placeholder="Explain the final decision..." />
            </div>
            <button className="btn btn-primary" style={{ width: '100%' }} onClick={resolveEscalatedDispute} disabled={saving || !decision || notes.length < 10}>
              {saving ? <span className="spinner" /> : 'Apply Final Resolution'}
            </button>
          </div>
        )}

        {/* MODALS */}
        {/* Credit Modal */}
        {creditModal && (
          <div style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.8)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 999, padding: '1rem' }}>
            <div className="card" style={{ width: '100%', maxWidth: 400, animation: 'fadeIn 0.2s ease' }}>
              <h3 style={{ marginBottom: '1rem', color: 'var(--gold)' }}>💰 Credit Coins</h3>
              <p style={{ marginBottom: '1rem', color: 'var(--text-muted)' }}>Crediting directly to <strong>{creditModal.name}</strong></p>
              
              <div className="input-group">
                <label className="input-label">Amount (₹)</label>
                <input className="input" type="number" value={creditAmount} onChange={(e) => setCreditAmount(e.target.value)} autoFocus />
              </div>
              <div className="input-group">
                <label className="input-label">Audit Reason</label>
                <input className="input" value={creditReason} onChange={(e) => setCreditReason(e.target.value)} placeholder="e.g. Compensation, Seeding" />
              </div>
              <div style={{ display: 'flex', gap: '0.5rem', marginTop: '1.5rem' }}>
                <button className="btn btn-primary" style={{ flex: 1 }} onClick={creditUser} disabled={saving || !creditAmount}>
                  {saving ? <span className="spinner" /> : 'Credit Now'}
                </button>
                <button className="btn btn-ghost" style={{ flex: 1 }} onClick={() => setCreditModal(null)}>Cancel</button>
              </div>
            </div>
          </div>
        )}

        {/* User Details Modal */}
        {userDetailsModal && (
          <div style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.85)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 999, padding: '1rem' }}>
            <div className="card" style={{ width: '100%', maxWidth: 800, maxHeight: '90vh', overflowY: 'auto', animation: 'fadeIn 0.2s ease' }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', borderBottom: '1px solid var(--border)', paddingBottom: '1rem', marginBottom: '1rem' }}>
                <div>
                  <h2 style={{ margin: 0, color: 'var(--gold)' }}>{userDetailsModal.user.full_name}</h2>
                  <div style={{ color: 'var(--text-muted)', fontSize: '0.9rem', marginTop: '0.25rem' }}>
                    {userDetailsModal.user.mobile_number} • {userDetailsModal.user.city || 'No City'} • Joined {new Date(userDetailsModal.user.created_at).toLocaleDateString()}
                  </div>
                </div>
                <button className="btn btn-sm btn-ghost" onClick={() => setUserDetailsModal(null)}>✕ Close</button>
              </div>

              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '1rem', marginBottom: '1.5rem' }}>
                <div style={{ background: 'rgba(255,255,255,0.03)', padding: '1rem', borderRadius: 8, textAlign: 'center' }}>
                  <div style={{ fontSize: '1.5rem', fontWeight: 700 }}>₹{userDetailsModal.user.wallet_balance}</div>
                  <div style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>Wallet Balance</div>
                </div>
                <div style={{ background: 'rgba(255,255,255,0.03)', padding: '1rem', borderRadius: 8, textAlign: 'center' }}>
                  <div style={{ fontSize: '1.5rem', fontWeight: 700 }}>{userDetailsModal.user.total_trades}</div>
                  <div style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>Total Trades</div>
                </div>
                <div style={{ background: 'rgba(255,255,255,0.03)', padding: '1rem', borderRadius: 8, textAlign: 'center' }}>
                  <div style={{ fontSize: '1.5rem', fontWeight: 700, color: userDetailsModal.user.reputation_score < 50 ? '#ef4444' : '#22c55e' }}>
                    {userDetailsModal.user.reputation_score}/100
                  </div>
                  <div style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>Reputation</div>
                </div>
              </div>

              <h3 style={{ marginBottom: '1rem' }}>📜 Recent Wallet History</h3>
              <div style={{ maxHeight: 200, overflowY: 'auto', marginBottom: '2rem', border: '1px solid var(--border)', borderRadius: 8 }}>
                {userDetailsModal.wallet_transactions.length === 0 ? <p style={{ padding: '1rem' }}>No history.</p> : (
                  <table style={{ width: '100%', textAlign: 'left', borderCollapse: 'collapse', fontSize: '0.9rem' }}>
                    <tbody>
                      {userDetailsModal.wallet_transactions.map((tx: any) => (
                        <tr key={tx.id} style={{ borderBottom: '1px solid rgba(255,255,255,0.05)' }}>
                          <td style={{ padding: '0.75rem' }}>{new Date(tx.created_at).toLocaleString()}</td>
                          <td style={{ padding: '0.75rem', fontWeight: 600 }}>{tx.type.replace('_', ' ')}</td>
                          <td style={{ padding: '0.75rem', color: tx.amount < 0 ? '#ef4444' : '#22c55e' }}>
                            {tx.amount > 0 ? '+' : ''}₹{tx.amount}
                          </td>
                          <td style={{ padding: '0.75rem', color: 'var(--text-muted)' }}>{tx.description_en}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                )}
              </div>

              <h3 style={{ marginBottom: '1rem' }}>💰 Daily Earnings Tracker</h3>
              <div style={{ maxHeight: 200, overflowY: 'auto', border: '1px solid var(--border)', borderRadius: 8 }}>
                {userDetailsModal.earnings.length === 0 ? <p style={{ padding: '1rem' }}>No earnings recorded.</p> : (
                  <table style={{ width: '100%', textAlign: 'left', borderCollapse: 'collapse', fontSize: '0.9rem' }}>
                    <tbody>
                      {userDetailsModal.earnings.map((e: any) => (
                        <tr key={e.id} style={{ borderBottom: '1px solid rgba(255,255,255,0.05)' }}>
                          <td style={{ padding: '0.75rem' }}>{new Date(e.date).toLocaleDateString()}</td>
                          <td style={{ padding: '0.75rem', fontWeight: 600, color: 'var(--gold)' }}>Earned: ₹{e.daily_earned}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                )}
              </div>
            </div>
          </div>
        )}

      </div>
    </AppLayout>
  );
}
