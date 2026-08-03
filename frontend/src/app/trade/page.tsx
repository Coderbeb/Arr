'use client';
import { Suspense } from 'react';
import TradeModule from '@/components/TradeModule';
import AppLayout from '@/components/AppLayout';
import { useLanguage } from '@/contexts/LanguageContext';

export default function TradePage() {
  const { t } = useLanguage();
  return (
    <AppLayout title={t('Trade Hub', 'ट्रेड हब')}>
      <Suspense fallback={
        <div style={{ display: 'flex', justifyContent: 'center', padding: '3rem' }}>
          <div className="spinner" style={{ width: 36, height: 36 }} />
        </div>
      }>
        <TradeModule />
      </Suspense>
    </AppLayout>
  );
}
