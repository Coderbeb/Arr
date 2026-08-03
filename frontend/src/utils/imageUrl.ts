/**
 * Resolves a relative upload path (e.g. `/uploads/trades/...`) to an absolute URL.
 * On the seller/assistance side, images uploaded by the buyer come back as relative paths
 * from the API server — the frontend needs to prefix them with the API base URL.
 */
const API_BASE = process.env.NEXT_PUBLIC_API_URL || '';

export function getFileUrl(relativePath: string | null | undefined): string {
  if (!relativePath) return '';
  // Already an absolute URL (e.g. from a blob or external CDN)
  if (relativePath.startsWith('http://') || relativePath.startsWith('https://') || relativePath.startsWith('blob:')) {
    return relativePath;
  }
  // Ensure API_BASE doesn't end with /api but with the server root
  // Since API = '/api' or 'http://localhost:3001/api', strip the /api suffix
  const base = API_BASE.replace(/\/api\/?$/, '');
  return `${base}${relativePath.startsWith('/') ? '' : '/'}${relativePath}`;
}

/**
 * Returns true if the given mime type or URL looks like a PDF.
 */
export function isPdf(urlOrMime: string | null | undefined): boolean {
  if (!urlOrMime) return false;
  return urlOrMime.endsWith('.pdf') || urlOrMime.includes('application/pdf');
}
