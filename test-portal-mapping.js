// Test script to verify portal mapping
const PORTAL_MAP = {
  logistics: "http://localhost:3004",
  inventoryManagement: "http://localhost:3005",
  telesales: "http://localhost:3006",
  delivery: "http://localhost:3007",
  accountant: "http://localhost:3008",
  cfo: "http://localhost:3009",
  gm: "http://localhost:3010",
  ceo: "http://localhost:3011",
  investor: "http://localhost:3013",
  finance: "http://localhost:3014",
  crm: "http://localhost:3015",
  books: "http://localhost:3017",
  kyc: "http://localhost:3018",
};

const roleToPortalMap = {
  'logistics': 'logistics',
  'inventory-management': 'inventoryManagement',
  'telesales': 'telesales',
  'delivery': 'delivery',
  'accountant': 'accountant',
  'cfo': 'cfo',
  'gm': 'gm',
  'ceo': 'ceo',
  'investor': 'investor',
  'finance': 'finance',
  'crm': 'crm',
  'books': 'books',
  'kyc': 'kyc'
};

console.log('=== Portal Mapping Test ===');
console.log('PORTAL_MAP entries:', Object.keys(PORTAL_MAP).length);
console.log('roleToPortalMap entries:', Object.keys(roleToPortalMap).length);

// Test each role mapping
Object.entries(roleToPortalMap).forEach(([role, portalKey]) => {
  const portalUrl = PORTAL_MAP[portalKey];
  console.log(`${role} → ${portalKey} → ${portalUrl}`);
});

console.log('\n=== Test Complete ==='); 