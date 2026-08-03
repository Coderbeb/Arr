/**
 * UPI Deep Link Generator for Arr Wallet
 * Generates deep links for all 4 supported UPI apps
 * 
 * Uses standard upi://pay intent which is the universal UPI scheme.
 * For specific apps, uses Android intent:// scheme with the correct package names.
 * On iOS, upi://pay will trigger the OS UPI app chooser.
 */

export interface UpiDeepLinks {
  gpay: string;
  phonepe: string;
  paytm: string;
  bhim: string;
  universal: string;
}

export function generateUpiDeepLinks(
  upiId: string,
  amount: number,
  tradeId: string
): UpiDeepLinks {
  const note = `ArrWallet-${tradeId.slice(0, 8)}`;
  const qs = `pa=${encodeURIComponent(upiId)}&am=${amount}&cu=INR&tn=${encodeURIComponent(note)}`;
  const base = `upi://pay?${qs}`;

  // Android intent:// scheme with package names for specific app targeting
  // Format: intent://pay?params#Intent;scheme=upi;package=com.app.package;end
  const intentBase = `intent://pay?${qs}#Intent;scheme=upi;action=android.intent.action.VIEW;category=android.intent.category.DEFAULT;category=android.intent.category.BROWSABLE`;

  return {
    // Universal UPI deep link (shows OS app chooser on Android, works on iOS)
    universal: base,

    // Google Pay (Tez)
    gpay: `${intentBase};package=com.google.android.apps.nbu.paisa.user;end`,

    // PhonePe
    phonepe: `${intentBase};package=com.phonepe.app;end`,

    // Paytm
    paytm: `${intentBase};package=net.one97.paytm;end`,

    // BHIM UPI
    bhim: `${intentBase};package=in.org.npci.upiapp;end`,
  };
}
