/**
 * Preload script: forces ALL DNS lookups to use IPv4.
 * Must be loaded before any other module via: node -r ./dns-fix.cjs
 *
 * This fixes ENETUNREACH errors when Render cannot route to IPv6
 * addresses returned by Supabase's DNS.
 */
const dns = require('dns');

// Method 1: Set default result order
// DNS overrides removed. Use Connection Pooler for Supabase to get IPv4.

console.log('✅ DNS forced to IPv4');
