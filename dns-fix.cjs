/**
 * Preload script: forces ALL DNS lookups to use IPv4.
 * Must be loaded before any other module via: node -r ./dns-fix.cjs
 *
 * This fixes ENETUNREACH errors when Render cannot route to IPv6
 * addresses returned by Supabase's DNS.
 */
const dns = require('dns');

// Method 1: Set default result order
if (dns.setDefaultResultOrder) {
  dns.setDefaultResultOrder('ipv4first');
}

// Method 2: Override dns.lookup to force family=4 (IPv4 only)
const origLookup = dns.lookup;
dns.lookup = function (hostname, options, callback) {
  if (typeof options === 'function') {
    callback = options;
    options = { family: 4 };
  } else if (typeof options === 'number') {
    options = { family: 4 };
  } else {
    options = Object.assign({}, options, { family: 4 });
  }
  return origLookup.call(this, hostname, options, callback);
};

console.log('✅ DNS forced to IPv4');
