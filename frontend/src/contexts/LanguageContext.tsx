'use client';
import React, { createContext, useContext, useState, useEffect } from 'react';

type Lang = 'en' | 'hi';

interface LanguageContextType {
  lang: Lang;
  setLang: (lang: Lang) => void;
  t: (en: string, hi: string) => string;
}

const LanguageContext = createContext<LanguageContextType>({
  lang: 'en',
  setLang: () => {},
  t: (en, hi) => en,
});

export function LanguageProvider({ children }: { children: React.ReactNode }) {
  const [lang, setLangState] = useState<Lang>('en');

  useEffect(() => {
    try {
      const storedLang = localStorage.getItem('lang') as Lang;
      if (storedLang === 'en' || storedLang === 'hi') {
        setLangState(storedLang);
      }
    } catch (e) {
      console.warn('localStorage access denied');
    }
  }, []);

  const setLang = (newLang: Lang) => {
    setLangState(newLang);
    try {
      localStorage.setItem('lang', newLang);
    } catch (e) {}
  };

  const t = (en: string, hi: string) => (lang === 'hi' ? hi : en);

  return (
    <LanguageContext.Provider value={{ lang, setLang, t }}>
      {children}
    </LanguageContext.Provider>
  );
}

export function useLanguage() {
  return useContext(LanguageContext);
}
