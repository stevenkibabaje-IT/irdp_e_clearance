'use strict';
// Run through responsive_test.php. Uses Node 22+ and installed Edge/Chromium, no npm dependencies.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const {spawn} = require('node:child_process');
const {once} = require('node:events');

const [origin, fixturePath] = process.argv.slice(2);
assert(origin && fixturePath, 'Run php tests/responsive_test.php to create isolated fixtures.');
const fixture = JSON.parse(fs.readFileSync(fixturePath, 'utf8'));
const edge = process.env.IRDP_BROWSER_BINARY || [
    process.env['PROGRAMFILES(X86)'] && path.join(process.env['PROGRAMFILES(X86)'], 'Microsoft/Edge/Application/msedge.exe'),
    process.env.PROGRAMFILES && path.join(process.env.PROGRAMFILES, 'Microsoft/Edge/Application/msedge.exe'),
    '/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome',
].find(candidate => candidate && fs.existsSync(candidate));
assert(edge, 'Set IRDP_BROWSER_BINARY to your installed Edge or Chromium executable.');
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'irdp-responsive-browser-'));
const screenshotDirectory = process.env.IRDP_SCREENSHOT_DIR;
if (screenshotDirectory) fs.mkdirSync(screenshotDirectory, {recursive: true});
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));
let browser, ws, stderr = '', checks = 0, tableChecks = 0;
let layoutRoute = null;
const pending = new Map(), listeners = new Map();
let nextId = 0;
const scriptErrors = [];
function send(method, params = {}) {
    return new Promise((resolve, reject) => {
        const id = ++nextId;
        const timer = setTimeout(() => {pending.delete(id); reject(new Error('Browser command timed out: '+method));}, 15000);
        pending.set(id, {resolve, reject, timer});
        ws.send(JSON.stringify({id, method, params}));
    });
}
function waitEvent(name) {
    return new Promise((resolve, reject) => {
        const timer = setTimeout(() => {listeners.delete(name); reject(new Error('Browser event timed out: '+name));}, 15000);
        listeners.set(name, data => {clearTimeout(timer); resolve(data);});
    });
}
async function evaluate(expression) {
    const result = await send('Runtime.evaluate', {expression, returnByValue: true, awaitPromise: true});
    if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text);
    return result.result.value;
}
async function viewport(width, height = 900) {
    await send('Emulation.setDeviceMetricsOverride', {width, height, deviceScaleFactor: 1, mobile: false});
    await evaluate('new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))');
}
async function navigate(route) {
    scriptErrors.length = 0;
    const loaded = waitEvent('Page.loadEventFired');
    const result = await send('Page.navigate', {url: origin+route});
    assert(!result.errorText, result.errorText);
    await loaded;
    await evaluate('document.fonts.ready.then(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))))');
    assert.equal(scriptErrors.length, 0, 'Browser JavaScript exception on '+route+': '+scriptErrors.join('; '));
    const state = await evaluate('({path:location.pathname,text:document.body.innerText,authenticated:!!document.querySelector(".app-shell"),headers:document.querySelectorAll(".institution-header").length,footers:document.querySelectorAll(".site-footer").length,logos:[...document.querySelectorAll(".institution-emblem,.institution-logo")].map(img=>img.complete && img.naturalWidth>0)})');
    assert.equal(state.path, route.split(/[?#]/)[0], 'Unexpected redirect for '+route);
    assert(!/Fatal error|Parse error|Warning:.*\.php|Access denied|not available for review/.test(state.text), 'Unexpected error page for '+route);
    assert.equal(state.headers, state.authenticated ? 0 : 1, 'Institutional header must appear on public pages only: '+route);
    assert.equal(state.footers, ['/', '/index.php'].includes(route.split(/[?#]/)[0]) ? 1 : 0, 'Footer must appear on the homepage only: '+route);
    assert.deepEqual(state.logos,state.authenticated ? [] : [true,true], 'Government/IRDP emblems did not load: '+route);
    const staffIdentity = await evaluate('({role:document.querySelector(".account-menu summary small")?.textContent.trim(),name:document.querySelector(".account-dropdown > strong")?.textContent.trim(),toolbar:document.querySelector(".topbar-title")?.textContent.trim()})');
    if (['Officer','Supervisor'].includes(staffIdentity.role)) assert.equal(staffIdentity.toolbar,staffIdentity.name,'Staff toolbar must show the signed-in name: '+route);
}
async function login(account) {
    layoutRoute = null;
    await send('Network.clearBrowserCookies');
    await navigate('/auth/login.php');
    await evaluate("localStorage.removeItem('irdp-accessibility')");
    await evaluate('document.getElementById("username").value='+JSON.stringify(account.username)+';document.getElementById("password").value='+JSON.stringify(account.password));
    const loaded = waitEvent('Page.loadEventFired');
    await evaluate('document.querySelector(".login-card form").requestSubmit()');
    await loaded;
    assert.notEqual(await evaluate('location.pathname'), '/auth/login.php', 'Fixture login failed.');
}
async function screenshot(name) {
    if (!screenshotDirectory) return;
    const result = await send('Page.captureScreenshot', {format: 'png', captureBeyondViewport: false});
    fs.writeFileSync(path.join(screenshotDirectory, name+'.png'), Buffer.from(result.data, 'base64'));
}
async function checkLayout(route, width, scale, height = width <= 480 ? 844 : 900) {
    await viewport(width, height);
    if (layoutRoute !== route) { await navigate(route); layoutRoute = route; }
    await evaluate('('+function(scale) {
        const select = document.getElementById('accessibilityTextSize');
        if (select) { select.value = String(scale); select.dispatchEvent(new Event('change')); }
        else document.documentElement.style.setProperty('--text-scale', String(scale));
        return new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
    }.toString()+')('+JSON.stringify(scale)+')');
    const dimensions = await evaluate('('+function() {
        const width = document.documentElement.clientWidth;
        const overflows = [...document.querySelectorAll('main, .institution-header, .institution-copy, .site-footer, .site-footer-grid > *, .panel, .stat-card, .quick-card, .stage-card, .table-wrap, .btn, input, select, textarea')]
            .filter(element => element.getClientRects().length && getComputedStyle(element).visibility !== 'hidden'
                && !element.closest('.sidebar') && !(element.closest('.table-wrap') && !element.matches('.table-wrap')))
            .filter(element => {const box = element.getBoundingClientRect(); return box.left < -1 || box.right > width + 1;})
            .map(element => element.tagName.toLowerCase()+'.'+element.className);
        const tables = [...document.querySelectorAll('.table-wrap')].map(wrapper => ({
            overflow: wrapper.scrollWidth > wrapper.clientWidth + 1, tabIndex: wrapper.tabIndex,
            hint: wrapper.previousElementSibling?.className === 'table-scroll-hint' && !wrapper.previousElementSibling.hidden,
        }));
        return {width, scrollWidth: document.documentElement.scrollWidth, overflows, tables};
    }.toString()+')()');
    const label = route+' at '+width+'px / '+(scale*100)+'% text';
    assert(dimensions.scrollWidth <= dimensions.width+1, 'Page scrolls sideways: '+label+' '+JSON.stringify(dimensions));
    assert.deepEqual(dimensions.overflows, [], 'Content exceeds screen: '+label);
    for (const table of dimensions.tables) {
        if (table.overflow) {assert.equal(table.tabIndex, 0, 'Overflow table must accept keyboard focus: '+label); assert(table.hint, 'Missing table scroll hint: '+label); tableChecks++;}
    }
    checks++;
    if (scale === 1 && width === 320 && route.includes('stage='+fixture.finance_stage)) {
        await screenshot(route.startsWith('/officer/') ? 'finance-review-mobile' : 'finance-receipt-mobile');
    }
    if (scale === 1 && [320, 768, 1440].includes(width) && /dashboard|status\.php/.test(route)) {
        await screenshot(route.split('/')[1]+'-'+width);
    }
    if (scale===1 && [390,1440].includes(width) && /auth\/(forgot|verify_otp|reset)\.php/.test(route)) {
        await screenshot(route.split('/').pop().replace('.php','')+'-'+width);
    }
}
async function key(key, modifiers = 0) {
    const windowsVirtualKeyCode = {Escape: 27, Tab: 9, Enter: 13}[key] || 0;
    await send('Input.dispatchKeyEvent', {type: 'keyDown', key, code: key, windowsVirtualKeyCode, modifiers, ...(key === 'Enter' ? {text: '\r'} : {})});
    await send('Input.dispatchKeyEvent', {type: 'keyUp', key, code: key, windowsVirtualKeyCode, modifiers});
}
async function waitFor(expression, label) {
    for (let attempt = 0; attempt < 100; attempt++) {
        if (await evaluate(expression)) return;
        await pause(50);
    }
    throw new Error('Timed out: '+label+' '+JSON.stringify(await evaluate('({path:location.href,text:document.querySelector("#main-content")?.innerText,invalid:[...document.querySelectorAll(":invalid")].map(e=>({id:e.id,message:e.validationMessage}))})')));
}
async function navigationChecks() {
    await viewport(320, 640); await navigate('/admin/dashboard.php');
    assert(await evaluate('document.getElementById("sidebar").inert'), 'Closed drawer must be inert.');
    await evaluate('document.getElementById("navigationToggle").click()');
    assert(await evaluate('document.getElementById("sidebar").classList.contains("open") && document.querySelector(".main-content").inert && !document.getElementById("navigationBackdrop").hidden && document.documentElement.classList.contains("navigation-open")'), 'Drawer must open, lock background and show backdrop.');
    assert.equal(await evaluate('document.activeElement.id'), 'navigationClose');
    await key('Tab', 8);
    assert(await evaluate('document.activeElement === [...document.querySelectorAll(".sidebar-nav a")].at(-1)'), 'Shift+Tab must stay inside navigation.');
    await key('Tab');
    assert.equal(await evaluate('document.activeElement.id'), 'navigationClose', 'Tab must stay inside navigation.');
    await key('Escape');
    assert(await evaluate('!document.querySelector(".main-content").inert && document.getElementById("sidebar").inert && document.getElementById("navigationBackdrop").hidden && document.activeElement.id === "navigationToggle"'), 'Escape must close the drawer and restore focus.');
    await evaluate('document.getElementById("navigationToggle").click()');
    await send('Input.dispatchMouseEvent', {type: 'mousePressed', x: 315, y: 200, button: 'left', clickCount: 1});
    await send('Input.dispatchMouseEvent', {type: 'mouseReleased', x: 315, y: 200, button: 'left', clickCount: 1});
    assert(await evaluate('!document.getElementById("sidebar").classList.contains("open")'), 'Backdrop click must close navigation.');
    await evaluate('document.getElementById("navigationToggle").click()');
    await viewport(1280); await pause(100);
    assert(await evaluate('!document.querySelector(".main-content").inert && !document.getElementById("sidebar").inert && !document.documentElement.classList.contains("navigation-open") && document.getElementById("navigationBackdrop").hidden'), 'Desktop resize must restore normal interaction and scroll.');
    await viewport(768); await pause(100);
    assert(await evaluate('document.getElementById("sidebar").inert && getComputedStyle(document.getElementById("navigationToggle")).display !== "none"'), 'Tablet must use the navigation drawer.');
    await navigate('/admin/programmes.php'); await viewport(320);
    assert(await evaluate('(() => {const table=document.querySelector(".table-wrap"); table.scrollLeft=120; return table.scrollLeft > 0;})()'), 'Table must scroll independently on a phone.');
    console.log('PASS: drawer focus, Escape/backdrop close, scroll lock, tablet breakpoint, resize and table scrolling');
}
async function shellChecks() {
    await viewport(1280);
    await navigate('/admin/dashboard.php');
    await evaluate('window.shellReference=document.querySelector(".app-shell"); window.shellMarker=Date.now()');
    await evaluate(`document.querySelector('.sidebar-nav a[href$="admin/programmes.php"]').click()`);
    await waitFor('location.pathname==="/admin/programmes.php" && !document.querySelector(".app-shell.is-loading")', 'Menu view loaded');
    assert(await evaluate('window.shellReference===document.querySelector(".app-shell")'), 'Menu replaced the application frame.');
    assert(await evaluate('document.querySelector(".sidebar-nav [aria-current=page]").href.endsWith("/admin/programmes.php")'), 'Active menu was not updated.');
    const code = 'SHELL'+Date.now();
    await evaluate('document.getElementById("code").value='+JSON.stringify(code)+'; document.getElementById("name").value="Shell navigation test"; document.getElementById("name").dispatchEvent(new Event("input",{bubbles:true})); document.querySelector("#main-content form").requestSubmit()');
    await waitFor('!document.querySelector(".app-shell.is-loading") && document.querySelector("#main-content").innerText.includes("Programme added.")', 'POST and redirect');
    assert(await evaluate('window.shellReference===document.querySelector(".app-shell") && document.querySelector("table").innerText.includes('+JSON.stringify(code)+')'), 'POST lost the frame or form values.');
    assert(await evaluate('document.querySelector("#main-content form").dataset.validationReady==="true"'), 'New forms did not initialize validation.');
    await evaluate(`document.querySelector('.sidebar-nav a[href$="admin/recovery.php"]').click()`);
    await waitFor('location.pathname==="/admin/recovery.php" && !document.querySelector(".app-shell.is-loading")', 'Second menu view');
    await evaluate('history.back()');
    await waitFor('location.pathname==="/admin/programmes.php" && !document.querySelector(".app-shell.is-loading")', 'Back view');
    assert(await evaluate('window.shellReference===document.querySelector(".app-shell")'), 'Back replaced the frame.');
    await evaluate('history.forward()');
    await waitFor('location.pathname==="/admin/recovery.php" && !document.querySelector(".app-shell.is-loading")', 'Forward view');
    assert.equal(scriptErrors.length, 0, 'Shell JavaScript errors: '+scriptErrors.join('; '));
    console.log('PASS: persistent application frame, active menu, form POST/redirect, reinitialized validation, Back and Forward');
}
async function studentShellChecks() {
    await viewport(1280);
    await navigate('/student/dashboard.php');
    assert.equal(await evaluate('document.querySelectorAll(".office-status-item").length'), 11, 'Dashboard must show all eleven offices together.');
    assert(await evaluate('!!document.querySelector(".student-clearance-layout [role=progressbar]") && !!document.querySelector(".student-clearance-layout .office-status-grid")'), 'Progress and office status must share the dashboard overview.');
    await viewport(1440,900); await screenshot('student-overview-desktop');
    assert(await evaluate('document.querySelector(".student-progress-overview").getBoundingClientRect().right < document.querySelector(".student-office-overview").getBoundingClientRect().left'), 'Desktop summary must sit beside office status.');
    await viewport(390,844); await screenshot('student-overview-mobile');
    await viewport(1280);
    assert(await evaluate('!document.querySelector("#main-content .profile-avatar") && !document.querySelector("#main-content").innerText.includes("Welcome,")'), 'Student identity remains in the dashboard.');
    assert(await evaluate('!document.querySelector("#main-content a[href*=notifications]") && !!document.querySelector(".topbar .notification-button")'), 'Dashboard notifications were not moved to the topbar.');
    assert(await evaluate('!document.querySelector(".office-detail-link") && !document.querySelector(".office-status-grid .icon-eye")'), 'Office cards still contain eye links.');
    assert(await evaluate('!document.querySelector(".sidebar form") && !document.querySelector(".topbar-logout") && !!document.querySelector(".account-menu .account-logout")'), 'Logout must be available in the account menu only.');
    assert(await evaluate(`![...document.querySelectorAll('.sidebar-nav a')].some(a=>/profile|change_password/.test(a.href))`), 'Student account links must stay in the top-right profile.');
    await evaluate(`window.shellReference=document.querySelector('.app-shell');document.querySelector('.account-menu summary').click();document.querySelector('.account-dropdown a[href$="student/profile.php"]').click()`);
    await waitFor('location.pathname==="/student/profile.php" && !document.querySelector(".app-shell.is-loading")', 'Profile menu');
    assert.match(await evaluate('document.getElementById("supportContext").innerText'), /Kusimamia profile/, 'Help context did not follow application navigation.');
    await evaluate('document.getElementById("phone").value="0712 345 678";document.getElementById("phone").dispatchEvent(new Event("input",{bubbles:true}));document.querySelector(".profile-phone-form").requestSubmit()');
    await waitFor('!document.querySelector(".app-shell.is-loading") && document.querySelector("#main-content").textContent.includes("Phone number saved.")','Profile phone save');
    assert.equal(await evaluate('document.getElementById("phone").value'),'+255712345678','Saved profile phone was not refreshed.');
    await viewport(1440,900); await screenshot('student-profile-desktop'); await viewport(390,844); await screenshot('student-profile-mobile'); await viewport(1280);
    await evaluate(`(() => {
        const transfer = new DataTransfer();
        transfer.items.add(new File([new Uint8Array([137,80,78,71])], 'preview.png', {type:'image/png'}));
        const input = document.getElementById('picture');
        input.files = transfer.files; input.dispatchEvent(new Event('change', {bubbles:true}));
    })()`);
    assert(await evaluate('document.getElementById("profilePreview").src.startsWith("blob:")'), 'Profile preview did not initialize after navigation.');
    await evaluate('document.querySelector(".notification-button").click()');
    await waitFor('location.pathname==="/student/notifications.php" && !document.querySelector(".app-shell.is-loading")', 'Notification view');
    assert(await evaluate('!!document.querySelector(".notification-card.is-unread") && !!document.querySelector(".notification-date")'), 'Notification status or date grouping is missing.');
    await viewport(1440,900); await screenshot('notifications-desktop');
    await viewport(390,844); await screenshot('notifications-mobile');
    await evaluate(`document.querySelector('.notification-filter[href*="view=unread"]').click()`);
    await waitFor('location.search.includes("view=unread") && !document.querySelector(".app-shell.is-loading")', 'Unread filter');
    assert(await evaluate('!document.querySelector(".notification-card.is-read")'), 'Unread filter includes read notices.');
    await evaluate('document.querySelector("#main-content form").requestSubmit()');
    await waitFor('!document.querySelector(".app-shell.is-loading") && !document.querySelector(".notification-count")', 'Unread count refreshed');
    assert(await evaluate('!document.querySelector(".notification-card.is-unread") && !!document.querySelector(".notification-card.is-read")'), 'Mark-all did not refresh notification card states.');
    await evaluate(`document.querySelector('.sidebar-nav a[href$="student/dashboard.php"]').click()`);
    await waitFor('location.pathname==="/student/dashboard.php" && !document.querySelector(".app-shell.is-loading")', 'Return to dashboard');
    await evaluate('document.querySelector(".rejection-action summary").click()');
    assert(await evaluate('document.querySelector(".rejection-action a").getClientRects().length>0'), 'Dashboard correction action must be available when expanded.');
    await evaluate('document.querySelector(".rejection-action a").click()');
    await waitFor('location.pathname==="/student/status.php" && !document.querySelector(".app-shell.is-loading")', 'Office deep link');
    assert(await evaluate('!!location.hash && document.activeElement.id===location.hash.slice(1) && window.shellReference===document.querySelector(".app-shell")'), 'Office anchor did not focus inside the retained frame.');
    assert(await evaluate('document.getElementById(location.hash.slice(1)).open && document.getElementById(location.hash.slice(1)).querySelector("input[type=file]").getClientRects().length>0'), 'An office link must reveal its correction form.');
    await evaluate('document.getElementById(location.hash.slice(1)).querySelector("summary").focus()');
    await key('Enter');
    assert(await evaluate('!document.getElementById(location.hash.slice(1)).open'), 'Office review must collapse with the keyboard.');
    await key('Enter');
    assert(await evaluate('document.getElementById(location.hash.slice(1)).open'), 'Office review must expand with the keyboard.');
    layoutRoute = '/student/status.php';
    await checkLayout('/student/status.php',320,2); await screenshot('student-review-expanded-mobile');
    await evaluate('document.getElementById(location.hash.slice(1)).open=false');
    await checkLayout('/student/status.php',1440,1); await screenshot('student-reviews-desktop');
    await checkLayout('/student/status.php',390,1); await screenshot('student-reviews-mobile');
    assert.equal(scriptErrors.length, 0, 'Student shell JavaScript errors: '+scriptErrors.join('; '));
    console.log('PASS: student profile in topbar, profile preview, separate notifications, unread count, office anchors');
}
async function officerSummaryChecks() {
    await viewport(1440,900); await navigate('/officer/dashboard.php');
    assert.equal(await evaluate('document.querySelector("#main-content h1").textContent.trim()'),fixture.officer.full_name,'Officer dashboard must show the signed-in name.');
    assert.deepEqual(await evaluate('[...document.querySelectorAll(".officer-summary .stat-card strong")].map(el=>el.textContent.trim())'),['2','1','2','1'],'Officer summary must count assigned pending work, returned evidence, today’s approvals and outstanding student corrections.');
    assert.equal(await evaluate('document.querySelectorAll(".officer-summary .stat-icon .icon").length'),4,'Work summaries must have meaningful icons.');
    await screenshot('officer-summary-desktop'); await viewport(390,844); await screenshot('officer-summary-mobile');
    await evaluate('document.querySelector(".officer-summary a[href*=returned]").click()');
    await waitFor('location.search.includes("view=returned") && !document.querySelector(".app-shell.is-loading")','Returned evidence filter');
    assert.equal(await evaluate('document.querySelectorAll("#assigned-tasks tbody tr").length'),1,'Returned-evidence filter must omit untouched pending reviews.');
    assert((await evaluate('document.querySelector("#assigned-tasks tbody").textContent')).includes(fixture.returned_registration),'Returned-evidence filter lost the student response.');
    await evaluate('document.querySelector(".task-filters a[href*=all]").click()');
    await waitFor('location.search.includes("view=all") && !document.querySelector(".app-shell.is-loading")','All pending filter');
    assert.equal(await evaluate('document.querySelectorAll("#assigned-tasks tbody tr").length'),2,'All pending reviews must be restored.');
    await navigate('/officer/review.php?stage='+fixture.review_stage);
    console.log('PASS: staff name across pages, scoped workload counts, summary icons and returned/all task filters');
}
async function landingChecks() {
    await navigate('/');
    await viewport(1440);
    await screenshot('home-desktop');
    await viewport(390,844);
    await screenshot('home-mobile');
    await evaluate('document.getElementById("supportToggle").click()');
    assert(await evaluate('!document.getElementById("supportPanel").hidden && document.getElementById("supportToggle").getAttribute("aria-expanded")==="true"'), 'Help did not open.');
    assert.match(await evaluate('document.getElementById("supportContext").innerText'), /Karibu kwenye e-Clearance/);
    await key('Escape');
    assert(await evaluate('document.getElementById("supportPanel").hidden && document.activeElement.id==="supportToggle"'), 'Escape did not close help and restore focus.');
    await evaluate('document.getElementById("supportToggle").click();document.getElementById("helpTabFaq").click();document.getElementById("helpSearch").value="receipt";document.getElementById("helpSearch").dispatchEvent(new Event("input"))');
    const search = await evaluate('({shown:document.querySelectorAll(".help-faq > details:not([hidden])").length,all:document.querySelectorAll(".help-faq > details").length,status:document.getElementById("helpSearchStatus").textContent})');
    assert(search.shown>0 && search.shown<search.all && search.status.includes(String(search.shown)), 'FAQ search did not filter and announce results.');
    await evaluate('document.getElementById("helpSearch").value="unmatched_xyz123";document.getElementById("helpSearch").dispatchEvent(new Event("input"))');
    assert(await evaluate('!document.getElementById("helpNoResults").hidden'), 'No-match guidance is missing.');
    await evaluate('document.getElementById("helpSearch").value="";document.getElementById("helpSearch").dispatchEvent(new Event("input"))');
    for (const tab of ['Guide','Faq','Contact']) {
        await evaluate('document.getElementById("helpTab'+tab+'").click()');
        for (const [width,height,scale] of [[320,844,2],[768,900,2],[1440,900,2],[2560,1440,2],[844,390,1],[844,390,2]]) {
            await viewport(width,height);
            await evaluate('document.documentElement.style.setProperty("--text-scale",'+JSON.stringify(String(scale))+');new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)))');
            const layout = await evaluate('(() => {const d=document.getElementById("supportPanel"), b=d.getBoundingClientRect(), body=d.querySelector(".help-panel-body");return {left:b.left,right:b.right,top:b.top,bottom:b.bottom,width:innerWidth,height:innerHeight,overflow:d.scrollWidth>d.clientWidth+1,bodyOverflow:body.scrollWidth>body.clientWidth+1,bodyHeight:body.clientHeight};})()');
            assert(layout.left>=-1 && layout.right<=layout.width+1 && layout.top>=0 && layout.bottom<=layout.height+1 && !layout.overflow && !layout.bodyOverflow && layout.bodyHeight>60, tab+' help layout at '+width+'x'+height+' / '+scale+': '+JSON.stringify(layout));
        }
    }
    assert.deepEqual(await evaluate('[...document.querySelectorAll("#helpContact a")].map(a=>a.getAttribute("href"))'), ['tel:+255659913570','tel:+255662632565']);
    await evaluate('document.getElementById("helpTabGuide").focus()');
    await key('ArrowRight');
    assert.equal(await evaluate('document.activeElement.id'), 'helpTabFaq', 'Tablist arrow navigation failed.');
    await evaluate('document.getElementById("supportClose").click()');
    await evaluate('document.documentElement.style.setProperty("--text-scale","1")');
    await viewport(1440,900);
    await evaluate('document.getElementById("supportToggle").click();document.getElementById("helpTabGuide").click()');
    await screenshot('help-guide-desktop');
    await evaluate('document.getElementById("helpTabFaq").click();document.querySelector(".help-faq summary").click()');
    await viewport(390,844);
    await screenshot('help-faq-mobile');
    await evaluate('document.getElementById("supportClose").click()');
    await navigate('/auth/login.php');
    await evaluate('document.getElementById("username").value="Unsaved username";document.getElementById("supportToggle").click()');
    assert.match(await evaluate('document.getElementById("supportContext").innerText'), /Kuingia kwenye mfumo/);
    await evaluate('document.getElementById("supportClose").click()');
    assert.equal(await evaluate('document.getElementById("username").value'), 'Unsaved username', 'Help discarded form input.');
    console.log('PASS: shared help, contextual guide, FAQ search/no results, original contacts, keyboard/focus, unsaved input, 320–2560px / 200% text and landscape');
}
async function accessibilityChecks() {
    await navigate('/');
    await evaluate('document.getElementById("accessibilityToggle").click()');
    await waitFor('document.getElementById("accessibilityToggle").getAttribute("aria-expanded")==="true"','Accessibility expanded');
    for (const [width,height] of [[320,844],[390,844],[768,900],[1440,900],[2560,1440],[844,390]]) {
        await viewport(width,height);
        for (const scale of ['1','2']) {
            await evaluate('document.getElementById("accessibilityTextSize").value='+JSON.stringify(scale)+';document.getElementById("accessibilityTextSize").dispatchEvent(new Event("change"));new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)))');
            const layout=await evaluate('(() => {const panel=document.getElementById("accessibilityPanel"),box=panel.getBoundingClientRect(),trigger=document.getElementById("accessibilityToggle").getBoundingClientRect(),help=document.getElementById("supportToggle").getBoundingClientRect();return {left:box.left,right:box.right,top:box.top,bottom:box.bottom,width:innerWidth,height:innerHeight,overflow:panel.scrollWidth>panel.clientWidth+1,overlap:trigger.right>help.left};})()');
            assert(layout.left>=0 && layout.right<=layout.width+1 && layout.top>=0 && layout.bottom<=layout.height+1 && !layout.overflow && !layout.overlap,'Accessibility controls exceed viewport: '+JSON.stringify({width,height,scale,layout}));
        }
    }
    await evaluate('document.getElementById("accessibilityContrast").click()');
    assert(await evaluate('document.documentElement.classList.contains("high-contrast") && document.getElementById("accessibilityContrast").getAttribute("aria-pressed")==="true"'),'Contrast toggle did not apply.');
    await navigate('/auth/login.php');
    assert(await evaluate('document.documentElement.classList.contains("high-contrast") && document.getElementById("accessibilityTextSize").value==="2"'),'Display settings did not persist.');
    await evaluate('document.getElementById("accessibilityToggle").click()');
    await evaluate('document.getElementById("accessibilityReset").click()');
    assert(await evaluate('!document.documentElement.classList.contains("high-contrast") && document.getElementById("accessibilityTextSize").value==="1"'),'Display reset failed.');
    await viewport(390,844); await screenshot('accessibility-mobile');
    await evaluate('document.getElementById("accessibilityClose").click()');
    assert(await evaluate('!document.getElementById("accessibilityTools").open && document.activeElement.id==="accessibilityToggle"'),'Close did not restore accessibility focus.');
    await evaluate('document.getElementById("accessibilityToggle").click();document.getElementById("accessibilityTextSize").focus()');
    await key('Escape');
    assert(await evaluate('!document.getElementById("accessibilityTools").open && document.activeElement.id==="accessibilityToggle"'),'Escape did not close accessibility.');
    await evaluate('document.getElementById("supportToggle").click();document.getElementById("accessibilityToggle").click()');
    await waitFor('document.getElementById("supportPanel").hidden','Accessibility replaces help panel');
    await evaluate('document.getElementById("accessibilityClose").click()');
    console.log('PASS: basic accessibility controls, 320–2560px/200%/landscape, persistent preferences, reset, focus/Escape and floating-button spacing');
}
async function sessionWarningChecks() {
    await login(fixture.new_student);
    await viewport(390,844);
    await navigate('/auth/change_password.php');
    await evaluate('document.getElementById("current_password").value="Unsaved form value"');
    const cookies=await send('Network.getCookies',{urls:[origin]});
    const session=cookies.cookies.find(cookie=>cookie.name==='PHPSESSID');
    assert(session && /^[a-zA-Z0-9,-]+$/.test(session.value),'Authenticated test session missing.');
    const sessionFile=path.join(fixture.session_directory,'sess_'+session.value);
    function age(values) {
        let data=fs.readFileSync(sessionFile,'utf8');
        for(const [key,value] of Object.entries(values)) {
            const expression=new RegExp(key+'\\|i:[0-9]+;');
            assert(expression.test(data),'Session timestamp missing: '+key);
            data=data.replace(expression,key+'|i:'+value+';');
        }
        fs.writeFileSync(sessionFile,data);
    }
    async function waitFor(expression,label) {
        for(let attempt=0;attempt<60;attempt++) {
            if(await evaluate(expression)) return;
            await pause(100);
        }
        throw new Error('Timed out: '+label);
    }
    const state=await evaluate('fetch(document.getElementById("sessionWarning").dataset.statusUrl).then(r=>r.json())');
    age({last_activity_at:state.server_time-120});
    // Advance only the browser's monotonic clock to exercise the minute heartbeat
    // without a real-minute wait; the server still uses its real clock and limits.
    await evaluate('window.sessionTestClock=performance.now.bind(performance);Object.defineProperty(performance,"now",{value:()=>window.sessionTestClock()+61000,configurable:true})');
    await key('Tab');
    await waitFor('fetch(document.getElementById("sessionWarning").dataset.statusUrl).then(r=>r.json()).then(s=>s.idle_expires_at>s.server_time+1700)','Trusted input heartbeat');
    await evaluate('delete performance.now;delete window.sessionTestClock');
    age({last_activity_at:state.server_time-1750});
    await evaluate('window.dispatchEvent(new Event("focus"))');
    await waitFor('document.getElementById("sessionWarning").open','Idle warning');
    assert.equal(await evaluate('document.getElementById("sessionContinue").hidden'),false,'Idle warning must offer continuation.');
    await key('Escape');
    assert.equal(await evaluate('document.getElementById("sessionWarning").open'),true,'Escape dismissed the timeout warning.');
    for(const width of [320,390,768,1440]) {
        await viewport(width,844);
        await evaluate('document.documentElement.style.setProperty("--text-scale","2")');
        const layout=await evaluate('new Promise(resolve=>requestAnimationFrame(()=>resolve((()=>{const d=document.getElementById("sessionWarning"),b=d.getBoundingClientRect();return {left:b.left,right:b.right,width:innerWidth,overflow:d.scrollWidth>d.clientWidth+1};})())))');
        assert(layout.left>=-1 && layout.right<=layout.width+1 && !layout.overflow,'Timeout warning overflows at '+width+'px / 200% text.');
    }
    await viewport(390,844);
    await evaluate('document.documentElement.style.setProperty("--text-scale","1")');
    await screenshot('session-warning-mobile');
    const beforeFailedContinue=await evaluate('fetch(document.getElementById("sessionWarning").dataset.statusUrl).then(r=>r.json())');
    await send('Network.setBlockedURLs',{urls:[origin+'/auth/session.php*']});
    await evaluate('document.getElementById("sessionContinue").click()');
    await waitFor('document.getElementById("sessionWarningError").textContent.length>0 && !document.getElementById("sessionContinue").disabled','Failed continuation feedback');
    assert.equal(await evaluate('document.getElementById("sessionWarning").open'),true,'A failed request silently dismissed the warning.');
    await send('Network.setBlockedURLs',{urls:[]});
    const afterFailedContinue=await evaluate('fetch(document.getElementById("sessionWarning").dataset.statusUrl).then(r=>r.json())');
    assert.equal(afterFailedContinue.idle_expires_at,beforeFailedContinue.idle_expires_at,'Failed continuation changed the server deadline.');
    await evaluate('document.getElementById("sessionContinue").click()');
    await waitFor('!document.getElementById("sessionWarning").open','Session continuation');
    assert.equal(await evaluate('document.getElementById("current_password").value'),'Unsaved form value','Continuation discarded the current form.');
    const renewed=await evaluate('fetch(document.getElementById("sessionWarning").dataset.statusUrl).then(r=>r.json())');
    assert(renewed.idle_expires_at>state.server_time+1700,'Continue did not renew the server deadline.');
    assert.equal(renewed.absolute_expires_at,state.absolute_expires_at,'Continue extended the absolute deadline.');
    age({session_started_at:renewed.server_time-28800+40,last_activity_at:renewed.server_time});
    await evaluate('window.dispatchEvent(new Event("focus"))');
    await waitFor('document.getElementById("sessionWarning").open && document.getElementById("sessionContinue").hidden','Absolute timeout warning');
    assert.match(await evaluate('document.getElementById("sessionWarningMessage").textContent'),/8-hour/,'Absolute warning did not explain the lifetime limit.');
    age({session_started_at:renewed.server_time-28801});
    await evaluate('window.dispatchEvent(new Event("focus"))');
    await waitFor('location.pathname==="/auth/login.php"','Absolute logout');
    assert.match(await evaluate('document.body.innerText'),/8-hour limit/,'Logout reason missing.');
    await login(fixture.new_student);
    await navigate('/auth/change_password.php');
    const secondCookies=await send('Network.getCookies',{urls:[origin]});
    const secondId=secondCookies.cookies.find(cookie=>cookie.name==='PHPSESSID').value;
    const secondFile=path.join(fixture.session_directory,'sess_'+secondId);
    const now=await evaluate('fetch(document.getElementById("sessionWarning").dataset.statusUrl).then(r=>r.json()).then(s=>s.server_time)');
    fs.writeFileSync(secondFile,fs.readFileSync(secondFile,'utf8').replace(/last_activity_at\|i:[0-9]+;/,'last_activity_at|i:'+(now-1801)+';'));
    await evaluate('window.dispatchEvent(new Event("focus"))');
    await waitFor('location.pathname==="/auth/login.php"','Idle logout');
    assert.match(await evaluate('document.body.innerText'),/30 minutes without activity/,'Idle logout reason missing.');
    console.log('PASS: trusted activity heartbeat, session warning, failed continuation, Escape protection, 320–1440px/200% text, continuation preserves forms, absolute and idle logout');
}
(async () => {
    try {
        browser = spawn(edge, ['--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
            '--disable-background-networking', '--disable-component-update', '--remote-debugging-port=0',
            '--remote-debugging-address=127.0.0.1', '--user-data-dir='+profile, 'about:blank'],
            {windowsHide: true, stdio: ['ignore', 'ignore', 'pipe']});
        browser.stderr.on('data', data => {stderr = (stderr+data.toString()).slice(-4000);});
        let spawnError;
        browser.on('error', error => {spawnError=error;});
        let port;
        for (let attempt=0; attempt<100; attempt++) {
            if (spawnError) throw spawnError;
            if (browser.exitCode !== null) throw new Error('Headless browser exited: '+stderr);
            const active = path.join(profile, 'DevToolsActivePort');
            if (fs.existsSync(active)) {
                try {
                    const candidate=fs.readFileSync(active,'utf8').split('\n')[0];
                    if (/^\d+$/.test(candidate)) {port=candidate;break;}
                } catch(error) {
                    // Edge briefly locks this file while publishing its port on Windows.
                    if (!['EBUSY','ENOENT'].includes(error.code)) throw error;
                }
            }
            await pause(100);
        }
        assert(port, 'Headless browser did not start: '+stderr);
        const targets = await fetch('http://127.0.0.1:'+port+'/json/list').then(response => response.json());
        const target = targets.find(target => target.type === 'page');
        assert(target, 'Browser page target unavailable.');
        ws = new WebSocket(target.webSocketDebuggerUrl);
        ws.addEventListener('message', event => {
            const message = JSON.parse(event.data);
            if (message.id) {
                const request = pending.get(message.id); if (!request) return;
                pending.delete(message.id); clearTimeout(request.timer);
                if (message.error) request.reject(new Error(message.error.message)); else request.resolve(message.result);
            } else {
                if (message.method === 'Runtime.exceptionThrown') scriptErrors.push(message.params.exceptionDetails.exception?.description || message.params.exceptionDetails.text);
                const listener = listeners.get(message.method);
                if (listener) {listeners.delete(message.method); listener(message.params);}
            }
        });
        await new Promise((resolve, reject) => {ws.addEventListener('open', resolve, {once: true}); ws.addEventListener('error', reject, {once: true});});
        await send('Page.enable'); await send('Runtime.enable'); await send('Network.enable');
        if (process.env.IRDP_SESSION_ONLY !== '1') {
            if (process.env.IRDP_STUDENT_ONLY !== '1') {
                await landingChecks();
                await accessibilityChecks();
                await login(fixture.admin); await shellChecks();
            }
            await login(fixture.student); await studentShellChecks();
            if (process.env.IRDP_STUDENT_ONLY !== '1') { await login(fixture.officer); await officerSummaryChecks(); }
            await send('Network.clearBrowserCookies');
        }
        const widths = [320, 360, 390, 480, 600, 768, 820, 1024, 1025, 1100, 1280, 1440, 1920, 2560];
        const groups = [
            {account: null, routes: ['/', '/auth/login.php', '/auth/forgot.php', '/auth/verify_otp.php', '/auth/reset.php', '/certificates/verify.php', '/transcripts/verify.php?token='+fixture.transcript_token]},
            {cookie: fixture.otp_session, routes: ['/auth/verify_otp.php']},
            {cookie: fixture.reset_session, routes: ['/auth/reset.php']},
            {account: fixture.admin, routes: ['/admin/dashboard.php', '/admin/users.php', '/admin/programmes.php', '/admin/offices.php', '/admin/departments.php', '/admin/workflow.php', '/admin/clearances.php', '/admin/clearance_period.php', '/admin/transcripts.php', '/admin/recovery.php', '/admin/import.php', '/admin/reports.php', '/auth/change_password.php']},
            {account: fixture.student, routes: ['/student/dashboard.php', '/student/status.php', '/student/resubmit.php?stage='+fixture.rejected_stage, '/student/profile.php', '/student/notifications.php']},
            {account: fixture.new_student, routes: ['/student/start.php', '/student/clearance_fee.php', '/student/dashboard.php', '/student/status.php']},
            {account: fixture.officer, routes: ['/officer/dashboard.php', '/officer/review.php?stage='+fixture.review_stage]},
            {account: fixture.finance_student, routes: ['/student/dashboard.php','/student/status.php','/student/resubmit.php?stage='+fixture.finance_stage]},
            {account: fixture.finance_officer, routes: ['/officer/dashboard.php','/officer/clearance_fees.php','/officer/clearance_fee_review.php?id='+fixture.entry_fee,'/officer/review.php?stage='+fixture.finance_stage]},
            {account: fixture.completed, routes: ['/student/dashboard.php', '/student/status.php']},
        ];
        const selectedGroups=process.env.IRDP_SESSION_ONLY==='1'?[{account:null,routes:['/']}]:process.env.IRDP_STUDENT_ONLY==='1'?groups.filter(group=>[fixture.student,fixture.new_student,fixture.finance_student,fixture.completed].includes(group.account)):groups;
        for (const group of selectedGroups) {
            if (group.account) await login(group.account);
            if (group.cookie) { await send('Network.clearBrowserCookies');await send('Network.setCookie',{name:'PHPSESSID',value:group.cookie,url:origin+'/',httpOnly:true,sameSite:'Lax'});layoutRoute=null; }
            if (group.account === fixture.finance_student) {
                await navigate('/student/dashboard.php');
                layoutRoute = '/student/dashboard.php';
                await evaluate('document.querySelector(".office-status-details summary").click()');
                assert(await evaluate('!!document.querySelector(".office-status-details[open] input[type=file]")'), 'Finance receipt upload must remain available in the compact dashboard.');
                await checkLayout('/student/dashboard.php', 320, 1);
                await checkLayout('/student/dashboard.php', 320, 2);
                await screenshot('student-payment-expanded-mobile');
                await evaluate('document.querySelector(".office-status-details").open=false');
                await navigate('/student/status.php#stage-'+fixture.finance_stage);
                layoutRoute = '/student/status.php';
                const officeState = await evaluate('({hash:location.hash,ids:[...document.querySelectorAll(".stage-card")].map(el=>el.id),open:!!document.getElementById(location.hash.slice(1))?.open,form:!!document.querySelector("details[open] input[type=file]")})');
                assert(officeState.open && officeState.form, 'Direct office links must reveal the Finance receipt form: '+JSON.stringify(officeState));
                await checkLayout('/student/status.php',320,1);
                await checkLayout('/student/status.php',320,2);
                await evaluate('document.querySelectorAll(".stage-card").forEach(el=>el.open=false)');
            }
            for (const route of group.routes) {
                for (const width of widths) await checkLayout(route, width, 1);
                for (const width of [320, 768, 1280, 1920]) await checkLayout(route, width, 2);
                await checkLayout(route, 844, 1, 390);
            }
            console.log('PASS: '+(group.account?.username || 'Public')+' responsive layouts');
            if (group.account === fixture.admin) await navigationChecks();
        }
        if (process.env.IRDP_STUDENT_ONLY !== '1') await sessionWarningChecks();
        console.log('PASS: '+checks+' browser layouts from 320–2560px, 100%/200% text and landscape, '+tableChecks+' scrollable tables');
    } catch (error) {
        console.error('FAIL: '+error.stack);
        try {if (ws?.readyState === WebSocket.OPEN) await screenshot('failure');} catch (_) {}
        process.exitCode = 1;
    } finally {
        try {if (ws?.readyState === WebSocket.OPEN) {await send('Browser.close'); ws.close();}} catch (_) {}
        if (browser && browser.exitCode === null) {
            await Promise.race([once(browser, 'exit'), pause(3000)]);
            if (browser.exitCode === null) {browser.kill(); await pause(300);}
        }
        // This recursively removes only the unique browser profile created above.
        const resolved = path.resolve(profile);
        if (path.dirname(resolved) === path.resolve(os.tmpdir()) && path.basename(resolved).startsWith('irdp-responsive-browser-')) {
            fs.rmSync(resolved, {recursive:true, force:true, maxRetries:5, retryDelay:200});
        }
    }
})();
