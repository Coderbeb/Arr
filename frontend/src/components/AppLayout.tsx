'use client';
import { useRouter, usePathname, useSearchParams } from 'next/navigation';
import { useState, Suspense } from 'react';
import { Home, ArrowRightLeft, Wallet, User, LayoutDashboard, Settings, Users, IndianRupee, ShieldAlert, BadgeAlert, Menu, Bell, LogOut } from 'lucide-react';
import { useLanguage } from '@/contexts/LanguageContext';
import { motion, AnimatePresence } from 'framer-motion';

interface NavItem {
  key: string;
  icon: React.ReactNode;
  en: string;
  hi: string;
  href: string;
}

const USER_NAV: NavItem[] = [
  { key: '/dashboard', icon: <Home size={20} />, en: 'Home', hi: 'होम', href: '/dashboard' },
  { key: '/trade',     icon: <ArrowRightLeft size={20} />, en: 'Trade', hi: 'ट्रेड', href: '/trade' },
  { key: '/wallet',    icon: <Wallet size={20} />, en: 'Wallet', hi: 'वॉलेट', href: '/wallet' },
  { key: '/profile',   icon: <User size={20} />, en: 'Profile', hi: 'प्रोफ़ाइल', href: '/profile' },
];

const ADMIN_NAV: NavItem[] = [
  { key: 'overview', icon: <LayoutDashboard size={20} />, en: 'Overview', hi: 'अवलोकन', href: '/admin?tab=overview' },
  { key: 'settings', icon: <Settings size={20} />, en: 'Settings', hi: 'सेटिंग्स', href: '/admin?tab=settings' },
  { key: 'users', icon: <Users size={20} />, en: 'Users', hi: 'उपयोगकर्ता', href: '/admin?tab=users' },
  { key: 'amounts', icon: <IndianRupee size={20} />, en: 'Amounts', hi: 'रकम', href: '/admin?tab=amounts' },
  { key: 'assistance', icon: <ShieldAlert size={20} />, en: 'Assistance', hi: 'सहायता', href: '/admin?tab=assistance' },
  { key: 'escalated', icon: <BadgeAlert size={20} />, en: 'Escalated', hi: 'एस्केलेटेड', href: '/admin?tab=escalated' },
];

const ASSISTANCE_NAV: NavItem[] = [
  { key: 'disputes', icon: <ShieldAlert size={20} />, en: '⚠️ Dispute Reviews', hi: '⚠️ विवाद समीक्षा', href: '/assistance?tab=disputes' },
  { key: 'trades',   icon: <ArrowRightLeft size={20} />, en: '📊 Live Trades', hi: '📊 लाइव ट्रेड', href: '/assistance?tab=trades' },
  { key: 'trade-hub',icon: <ArrowRightLeft size={20} />, en: '🛒 Buy / Sell', hi: '🛒 खरीदें / बेचें', href: '/assistance?tab=trade-hub' },
  { key: 'sell',     icon: <IndianRupee size={20} />, en: '📤 Seed Market', hi: '📤 बाजार सीड', href: '/assistance?tab=sell' },
  { key: 'profile',  icon: <User size={20} />, en: '👤 Profile', hi: '👤 प्रोफ़ाइल', href: '/profile' },
];

function AppLayoutInner({ children, role = 'user', title }: { children: React.ReactNode, role?: 'user' | 'admin' | 'assistance', title?: string }) {
  const router = useRouter();
  const pathname = usePathname();
  const { lang, t } = useLanguage();
  const [unreadCount, setUnreadCount] = useState(0);
  const [isSidebarOpen, setIsSidebarOpen] = useState(false);

  const searchParams = useSearchParams();
  const currentTab = searchParams.get('tab') || 'disputes';

  const savedRole = typeof window !== 'undefined' ? localStorage.getItem('role') as any : 'user';
  const actualRole = (role === 'user' && savedRole === 'assistance') ? 'assistance' : role;
  const navItems = actualRole === 'admin' ? ADMIN_NAV : actualRole === 'assistance' ? ASSISTANCE_NAV : USER_NAV;
  
  let activeKey = navItems[0].href;
  if (actualRole === 'admin') {
    activeKey = navItems.find(item => item.key === currentTab)?.href || navItems[0].href;
  } else if (actualRole === 'assistance') {
    activeKey = navItems.find(item => item.key === currentTab || item.href.includes(`tab=${currentTab}`) || pathname.startsWith(item.href))?.href || (pathname.startsWith('/profile') ? '/profile' : navItems[0].href);
  } else {
    activeKey = navItems.find(item => pathname.startsWith(item.href))?.href || navItems[0].href;
  }

  function handleLogout() {
    localStorage.clear();
    router.push('/');
  }

  return (
    <div className="app-layout">
      <div className="bg-orb-1"></div>
      <div className="bg-orb-2"></div>

      <AnimatePresence>
        {isSidebarOpen && (
          <motion.div 
            initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}
            className="sidebar-overlay"
            onClick={() => setIsSidebarOpen(false)}
            style={{ display: 'block' }} // Override CSS none for animation
          />
        )}
      </AnimatePresence>

      <aside className={`sidebar-nav ${isSidebarOpen ? 'open' : ''}`}>
        <div className="sidebar-logo">
          <div className="auth-logo-text" style={{ fontSize: '1.5rem', textAlign: 'left' }}>🪙 Arr Wallet</div>
        </div>
        
        <div className="sidebar-links">
          {navItems.map((item) => {
            const isActive = activeKey === item.href;
            return (
              <button
                key={item.key}
                className={`sidebar-item ${isActive ? 'active' : ''}`}
                onClick={() => {
                  setIsSidebarOpen(false);
                  router.push(item.href);
                }}
              >
                <span className={`icon ${isActive ? 'text-gold' : ''}`}>{item.icon}</span>
                <span className="label">{t(item.en, item.hi)}</span>
                {isActive && (
                  <motion.div layoutId="sidebar-active" className="sidebar-active-bg" />
                )}
              </button>
            );
          })}
        </div>

        <div className="sidebar-footer">
          <button className="sidebar-item logout-btn" onClick={handleLogout}>
            <span className="icon"><LogOut size={20} /></span>
            <span className="label">{t('Logout', 'लॉगआउट')}</span>
          </button>
        </div>
      </aside>

      <main className="main-content">
        <div className="topbar mobile-only glass">
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
            <button className="btn btn-ghost btn-sm" onClick={() => setIsSidebarOpen(true)} style={{ padding: '0.2rem 0.5rem' }}>
              <Menu size={24} />
            </button>
            <div className="topbar-title">{title || '🪙 Arr Wallet'}</div>
          </div>
          <div style={{ display: 'flex', alignItems: 'center', gap: '1rem' }}>
            {unreadCount > 0 && (
              <div style={{ position: 'relative', cursor: 'pointer', color: 'var(--text-primary)' }}>
                <Bell size={20} />
                <span className="badge-notif">{unreadCount}</span>
              </div>
            )}
            <button className="btn btn-ghost btn-sm" style={{ padding: '0.2rem 0.5rem' }} onClick={handleLogout}>
              <LogOut size={20} />
            </button>
          </div>
        </div>

        <motion.div 
          initial={{ opacity: 0, y: 10 }} 
          animate={{ opacity: 1, y: 0 }} 
          exit={{ opacity: 0, y: -10 }}
          transition={{ duration: 0.3 }}
          className="page-inner"
        >
          {children}
        </motion.div>
      </main>
    </div>
  );
}

export default function AppLayout(props: { children: React.ReactNode, role?: 'user' | 'admin' | 'assistance', title?: string }) {
  return (
    <Suspense fallback={
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', minHeight: '100vh' }}>
        <div className="spinner" style={{ width: 48, height: 48, borderColor: 'var(--gold) transparent var(--gold) transparent' }} />
      </div>
    }>
      <AppLayoutInner {...props} />
    </Suspense>
  );
}
