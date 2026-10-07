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
    const state = await evaluate('({path:location.pathname,text:document.body.innerText})');
    assert.equal(state.path, route.split('?')[0], 'Unexpected redirect for '+route);
    assert(!/Fatal error|Parse error|Warning:.*\.php|Access denied|not available for review/.test(state.text), 'Unexpected error page for '+route);
}
async function login(account) {
    layoutRoute = null;
    await send('Network.clearBrowserCookies');
    await evaluate("localStorage.removeItem('irdp-accessibility')");
    await navigate('/auth/login.php');
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
async function checkLayout(route, width, scale) {
    await viewport(width, width <= 480 ? 844 : 900);
    if (layoutRoute !== route) { await navigate(route); layoutRoute = route; }
    await evaluate('('+function(scale) {
        const select = document.getElementById('accessibilityTextSize');
        if (select) { select.value = String(scale); select.dispatchEvent(new Event('change')); }
        else document.documentElement.style.setProperty('--text-scale', String(scale));
        return new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
    }.toString()+')('+JSON.stringify(scale)+')');
    const dimensions = await evaluate('('+function() {
        const width = document.documentElement.clientWidth;
        const overflows = [...document.querySelectorAll('main, .panel, .stat-card, .quick-card, .stage-card, .table-wrap, .btn, input, select, textarea')]
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
}
async function key(key, modifiers = 0) {
    await send('Input.dispatchKeyEvent', {type: 'keyDown', key, code: key, modifiers});
    await send('Input.dispatchKeyEvent', {type: 'keyUp', key, code: key, modifiers});
}
async function navigationChecks() {
    await viewport(320, 640); await navigate('/admin/dashboard.php');
    assert(await evaluate('document.getElementById("sidebar").inert'), 'Closed drawer must be inert.');
    await evaluate('document.getElementById("navigationToggle").click()');
    assert(await evaluate('document.getElementById("sidebar").classList.contains("open") && document.querySelector(".main-content").inert && !document.getElementById("navigationBackdrop").hidden && document.documentElement.classList.contains("navigation-open")'), 'Drawer must open, lock background and show backdrop.');
    assert.equal(await evaluate('document.activeElement.id'), 'navigationClose');
    await key('Tab', 8);
    assert(await evaluate('document.activeElement === document.querySelector(".sidebar-nav form button")'), 'Shift+Tab must stay inside navigation.');
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
        const widths = [320, 390, 480, 600, 768, 1024, 1025, 1100, 1280, 1440];
        const groups = [
            {account: null, routes: ['/', '/auth/login.php', '/auth/forgot.php', '/auth/reset.php', '/certificates/verify.php', '/transcripts/verify.php?token='+fixture.transcript_token]},
            {account: fixture.admin, routes: ['/admin/dashboard.php', '/admin/users.php', '/admin/programmes.php', '/admin/offices.php', '/admin/departments.php', '/admin/workflow.php', '/admin/clearances.php', '/admin/clearance_period.php', '/admin/transcripts.php', '/admin/recovery.php', '/admin/import.php', '/admin/reports.php', '/auth/change_password.php']},
            {account: fixture.student, routes: ['/student/dashboard.php', '/student/status.php', '/student/resubmit.php?stage='+fixture.rejected_stage, '/student/profile.php', '/student/notifications.php']},
            {account: fixture.new_student, routes: ['/student/start.php', '/student/clearance_fee.php', '/student/dashboard.php', '/student/status.php']},
            {account: fixture.officer, routes: ['/officer/dashboard.php', '/officer/review.php?stage='+fixture.review_stage]},
            {account: fixture.finance_student, routes: ['/student/dashboard.php','/student/status.php','/student/resubmit.php?stage='+fixture.finance_stage]},
            {account: fixture.finance_officer, routes: ['/officer/dashboard.php','/officer/clearance_fees.php','/officer/clearance_fee_review.php?id='+fixture.entry_fee,'/officer/review.php?stage='+fixture.finance_stage]},
            {account: fixture.completed, routes: ['/student/dashboard.php', '/student/status.php']},
        ];
        const selectedGroups=process.env.IRDP_SESSION_ONLY==='1'?[{account:null,routes:['/']}]:groups;
        for (const group of selectedGroups) {
            if (group.account) await login(group.account);
            for (const route of group.routes) {
                for (const width of widths) await checkLayout(route, width, 1);
                for (const width of [320, 768, 1280]) await checkLayout(route, width, 2);
            }
            console.log('PASS: '+(group.account?.username || 'Public')+' responsive layouts');
            if (group.account === fixture.admin) await navigationChecks();
        }
        await sessionWarningChecks();
        console.log('PASS: '+checks+' browser layouts from 320–1440px, 100%/200% text, '+tableChecks+' scrollable tables');
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
