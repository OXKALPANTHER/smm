(function () {
  'use strict';
  const pairs = {
    'nav.home': ['Home', 'Nyumbani'],
    'nav.services': ['Services', 'Huduma'],
    'nav.newOrder': ['New Order', 'Order Mpya'],
    'nav.orders': ['Orders', 'Maagizo'],
    'nav.ordersShort': ['Orders', 'Maagizo'],
    'nav.account': ['Account', 'Akaunti'],
    'nav.dashboard': ['Dashboard', 'Dashibodi'],
    'nav.favorites': ['Favorites', 'Vipendwa'],
    'nav.notifications': ['Notifications', 'Arifa'],
    'nav.pro': ['Pro Dashboard', 'Dashibodi Pro'],
    'nav.api': ['API Center', 'Kituo cha API'],
    'nav.topup': ['Add Balance', 'Ongeza Salio'],
    'nav.guide': ['Guide', 'Mwongozo'],
    'nav.admin': ['Admin Panel', 'Jopo la Admin'],
    'nav.moderator': ['Moderator Desk', 'Dawati la Msimamizi'],
    'nav.support': ['Help & community', 'Msaada na jamii'],
    'nav.whatsappGroup': ['WhatsApp Group', 'Kikundi cha WhatsApp'],
    'nav.whatsappChannel': ['WhatsApp Channel', 'Kituo cha WhatsApp'],
    'nav.helpLegal': ['Help & legal', 'Msaada na sheria'],
    'nav.logout': ['Log out', 'Toka'],
    'common.balance': ['Balance', 'Salio'],
    'common.cancel': ['Cancel', 'Ghairi'],
    'common.greeting': ['Hello,', 'Habari,'],
    'common.yourBalance': ['YOUR BALANCE', 'SALIO LAKO'],
    'verification.pricing': ['Verified pricing', 'Bei za akaunti iliyothibitishwa'],
    'verify.nudgeTitle': ['Unlock lower verified-account prices', 'Fungua bei nafuu za akaunti iliyothibitishwa'],
    'verify.nudgeBody': ['Verify once to access the lower verified pricing tier and a verified account badge.', 'Thibitisha mara moja ili kupata bei nafuu na beji ya akaunti iliyothibitishwa.'],
    'verify.benefits': ['Explore benefits', 'Angalia faida'],
    'stats.orders': ['Orders', 'Maagizo'],
    'stats.pending': ['Pending', 'Zinasubiri'],
    'stats.done': ['Done', 'Zimekamilika'],
    'header.home.title': ['Dashboard', 'Dashibodi'],
    'header.home.subtitle': ['Your account at a glance', 'Muhtasari wa akaunti yako'],
    'header.services.title': ['Services', 'Huduma'],
    'header.services.subtitle': ['Browse the available catalogue', 'Vinjari huduma zinazopatikana'],
    'header.orders.title': ['Orders', 'Maagizo'],
    'header.orders.subtitle': ['Track and manage your orders', 'Fuatilia na simamia maagizo yako'],
    'header.account.title': ['Account', 'Akaunti'],
    'header.account.subtitle': ['Profile and account settings', 'Wasifu na mipangilio ya akaunti'],
    'header.favorites.title': ['Favorites', 'Vipendwa'],
    'header.favorites.subtitle': ['Your saved orders', 'Maagizo uliyohifadhi'],
    'header.notifications.title': ['Notifications', 'Arifa'],
    'header.notifications.subtitle': ['Your recent updates', 'Taarifa zako za hivi karibuni'],
    'header.api.title': ['API Center', 'Kituo cha API'],
    'header.api.subtitle': ['Manage developer API access', 'Simamia ufikiaji wa API'],
    'header.guide.title': ['Guide', 'Mwongozo'],
    'header.guide.subtitle': ['Help for using the panel', 'Msaada wa kutumia paneli'],
    'header.moderator.title': ['Moderator Desk', 'Dawati la Msimamizi'],
    'header.moderator.subtitle': ['Review moderation tasks', 'Kagua kazi za usimamizi'],
    'orders.rate': ['Rate this order', 'Kadiria agizo hili'],
    'orders.rateTitle': ['Rate this order', 'Kadiria agizo hili'],
    'orders.reviewPending': ['Your review is private until it is approved.', 'Tathmini yako itabaki faragha hadi iidhinishwe.'],
    'orders.rating': ['Your rating', 'Ukadiriaji wako'],
    'orders.displayName': ['Public name (first name + last initial)', 'Jina la kuonyesha (jina la kwanza + herufi ya mwisho)'],
    'orders.reviewText': ['Comment (20–300 characters; no links)', 'Maoni (herufi 20–300; bila viungo)'],
    'orders.reviewNoLinks': ['HTML is removed; links are not allowed.', 'HTML itaondolewa; viungo haviruhusiwi.'],
    'orders.consent': ['I allow Royal SMM to show this review with my first name and initial.', 'Ninaruhusu Royal SMM kuonyesha tathmini hii kwa jina langu la kwanza na herufi ya jina la mwisho.'],
    'orders.submitReview': ['Submit review', 'Tuma tathmini'],
    'order.title': ['Place a New Order', 'Weka Order Mpya'],
    'order.platform': ['1. Find or Choose a Platform', '1. Tafuta au Chagua Platform'],
    'order.service': ['2. Choose a Service', '2. Chagua Huduma'],
    'order.quantity': ['4. Quantity', '4. Idadi'],
    'order.searchPlaceholder': ['Search by name, platform or Service ID (e.g. 5557)...', 'Tafuta jina, platform au Service ID (mf: 5557)...'],
    'order.clearSearch': ['Clear search', 'Futa'],
    'order.exitPro': ['Return to standard services', 'Rudi kawaida'],
    'order.proTitle': ['Looking for Pro services?', 'Unatafuta Huduma Pro'],
    'order.proBody': ['Search for an alternative service above, then try your order again.', 'Tafuta huduma mbadala hapo juu kisha weka order tena.'],
    'order.choosePlatform': ['or choose a platform', 'au chagua platform'],
    'order.choosePlatformFirst': ['Choose a platform first...', 'Chagua platform kwanza...'],
    'order.linkPlaceholder': ['Choose a service first...', 'Chagua huduma kwanza...'],
    'order.linkHint': ['Choose a service to see the correct link instructions.', 'Chagua huduma ili kuona maelekezo sahihi ya link.'],
    'order.previewTitle': ['Link preview', 'Hakiki ya link'],
    'order.openLink': ['Open link', 'Fungua link'],
    'order.total': ['Total to pay', 'Jumla ya Malipo'],
    'order.insufficient': ['Insufficient balance', 'Salio halitoshi'],
    'orders.recent': ['Recent Orders', 'Orders za Karibuni'],
    'orders.refresh': ['Refresh', 'Sasisha'],
    'orders.all': ['All', 'Zote'],
    'account.helpLegalIntro': ['Quick links for services, account guidance, and site policies.', 'Viungo vya haraka vya huduma, mwongozo wa akaunti na sera za tovuti.'],
    'account.services': ['Services', 'Huduma'],
    'account.pricing': ['Pricing', 'Bei'],
    'account.resellerApi': ['Reseller API', 'API ya Reseller'],
    'account.terms': ['Terms', 'Masharti'],
    'account.privacy': ['Privacy', 'Faragha'],
    'account.refundRefill': ['Refund / Refill', 'Marejesho / Refill'],
    'support.button': ['Support', 'Msaada'],
    'theme.system': ['Theme: System', 'Mandhari: Mfumo'],
    'theme.dark': ['Theme: Dark', 'Mandhari: Giza'],
    'theme.light': ['Theme: Light', 'Mandhari: Mwanga'],
    'order.submit': ['CONFIRM ORDER', 'THIBITISHA ORDER'],
    'order.hint.select': ['Select a service, then enter quantity and link.', 'Chagua huduma kwanza, kisha uingize kiasi na link.'],
    'order.hint.quantity': ['Enter a quantity to see your order estimate.', 'Ingiza kiasi cha wateja ili kuona makadirio ya order.'],
    'order.hint.range': ['Enter a quantity within the service limits.', 'Ingiza kiasi kinachokubalika kwa huduma hii.'],
    'order.hint.balance': ['Balance is insufficient. Add funds before continuing.', 'Salio halitoshi. Ongeza salio kabla ya kuendelea.'],
    'order.hint.link': ['Add the post/profile link or username before submitting.', 'Weka link ya post/profaili au username kabla ya kutuma.'],
    'order.hint.ready': ['Details are ready. Review the link, then confirm your order.', 'Taarifa ziko tayari. Hakiki link na kisha thibitisha order.']
  };

  const phrasePairs = [
    ['Maagizo Yangu', 'My Orders'], ['Sasisha', 'Refresh'], ['Tafuta order kwa ID, huduma, au link...', 'Search orders by ID, service, or link...'],
    ['3. Weka Link ya Post / Video', '3. Enter Post / Video Link'], ['3. Weka Link ya Profile / Username', '3. Enter Profile / Username'],
    ['Chagua huduma ili kuona maelekezo sahihi ya link.', 'Choose a service to see the correct link instructions.'],
    ['Weka link KAMILI ya post / video / reel husika.', 'Enter the full link to the relevant post / video / reel.'],
    ['Weka profile/username — akaunti iwe PUBLIC.', 'Enter the profile/username — the account must be PUBLIC.'],
    ['Weka link sahihi ya huduma uliyochagua.', 'Enter the correct link for the selected service.'],
    ['Order Mpya', 'New Order'], ['Omba Refill', 'Request refill'], ['Reorder', 'Agiza tena'], ['Favorite', 'Kipendwa'], ['Saved', 'Imehifadhiwa'], ['Back to Admin', 'Rudi kwa Admin'], ['Add to moderation queue', 'Ongeza kwenye foleni ya ukaguzi'],
    ['Zote', 'All'], ['Inasubiri / Inaendelea', 'Pending / Processing'], ['Imekamilika', 'Completed'], ['Imeghairiwa', 'Cancelled'],
    ['Huduma Kawaida', 'Standard services'], ['Huduma Pro', 'Pro services'], ['Hakuna orders katika huduma hii.', 'No orders in this service.'],
    ['Hakuna orders katika kundi hili.', 'No orders in this group.'], ['Omba Refill', 'Request refill'], ['Taarifa za Akaunti', 'Account information'],
    ['Karibisha Marafiki', 'Invite friends'], ['Badilisha Neno Siri', 'Change password'], ['Historia ya Orders', 'Order history'],
    ['Namba ya Simu', 'Phone number'], ['Share kwa WhatsApp', 'Share on WhatsApp'], ['Badilisha', 'Change password'],
    ['Neno siri la zamani', 'Current password'], ['Neno siri jipya (min', 'New password (min'], ['Order Mpya', 'New Order'],
    ['Kadiria agizo hili', 'Rate this order'], ['Tuma tathmini', 'Submit review'], ['Cancel', 'Ghairi'], ['Ghairi', 'Cancel'],
    ['Account', 'Account'], ['Services', 'Services'], ['Orders', 'Orders'], ['Home', 'Home'], ['Favorites', 'Favorites'], ['Notifications', 'Notifications']
  ];
  const phraseMap = new Map();
  phrasePairs.forEach(([sw, en]) => {
    phraseMap.set(sw, { en, sw });
    phraseMap.set(en, { en, sw });
  });

  function currentLanguage() {
    try {
      const stored = localStorage.getItem('royal-language');
      if (stored === 'en' || stored === 'sw') return stored;
    } catch (_) {}
    return document.documentElement.lang === 'sw' ? 'sw' : 'en';
  }

  function preserveSpace(original, replacement) {
    const before = (original.match(/^\s*/) || [''])[0];
    const after = (original.match(/\s*$/) || [''])[0];
    return before + replacement + after;
  }

  function applyLanguage(language) {
    const lang = language === 'sw' ? 'sw' : 'en';
    document.documentElement.lang = lang;
    document.querySelectorAll('[data-i18n]').forEach((element) => {
      const pair = pairs[element.dataset.i18n];
      if (pair) element.textContent = pair[lang === 'sw' ? 1 : 0];
    });
    document.querySelectorAll('[data-i18n-placeholder]').forEach((element) => {
      const pair = pairs[element.dataset.i18nPlaceholder];
      if (pair) element.setAttribute('placeholder', pair[lang === 'sw' ? 1 : 0]);
    });
    document.querySelectorAll('[data-i18n-aria-label]').forEach((element) => {
      const pair = pairs[element.dataset.i18nAriaLabel];
      if (pair) element.setAttribute('aria-label', pair[lang === 'sw' ? 1 : 0]);
    });
    document.querySelectorAll('[data-set-language]').forEach((button) => {
      button.setAttribute('aria-pressed', String(button.dataset.setLanguage === lang));
    });
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, {
      acceptNode(node) {
        const parent = node.parentElement;
        if (!parent || parent.closest('script,style,textarea,option,[data-no-i18n],[data-i18n]')) return NodeFilter.FILTER_REJECT;
        return NodeFilter.FILTER_ACCEPT;
      }
    });
    const nodes = [];
    while (walker.nextNode()) nodes.push(walker.currentNode);
    nodes.forEach((node) => {
      const trimmed = node.nodeValue.trim();
      const pair = phraseMap.get(trimmed);
      if (pair) node.nodeValue = preserveSpace(node.nodeValue, pair[lang]);
    });
    try { localStorage.setItem('royal-language', lang); } catch (_) {}
  }

  let themeChoice = 'system';
  try { themeChoice = localStorage.getItem('royal-theme') || 'system'; } catch (_) {}
  const systemThemeMedia = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

  function applyTheme(choice) {
    const root = document.documentElement;
    const selected = ['light', 'dark'].includes(choice) ? choice : 'system';
    themeChoice = selected;
    root.dataset.theme = selected === 'system' ? (systemThemeMedia && systemThemeMedia.matches ? 'dark' : 'light') : selected;
    try {
      if (selected === 'system') localStorage.removeItem('royal-theme');
      else localStorage.setItem('royal-theme', selected);
    } catch (_) {}
    const button = document.querySelector('[data-toggle-theme]');
    if (button) button.dataset.i18n = 'theme.' + selected;
    applyLanguage(currentLanguage());
  }

  document.addEventListener('click', (event) => {
    const languageButton = event.target.closest('[data-set-language]');
    if (languageButton) applyLanguage(languageButton.dataset.setLanguage);
    const themeButton = event.target.closest('[data-toggle-theme]');
    if (themeButton) {
      const current = themeChoice;
      applyTheme(current === 'system' ? 'dark' : current === 'dark' ? 'light' : 'system');
    }
  });
  if (systemThemeMedia) {
    const handleSystemThemeChange = () => { if (themeChoice === 'system') applyTheme('system'); };
    if (systemThemeMedia.addEventListener) systemThemeMedia.addEventListener('change', handleSystemThemeChange);
    else if (systemThemeMedia.addListener) systemThemeMedia.addListener(handleSystemThemeChange);
  }
  applyTheme(themeChoice);
  window.royalSetLanguage = applyLanguage;
  applyLanguage(currentLanguage());
})();
