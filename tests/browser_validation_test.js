// Run with Node.js. Exercises the real browser validation script with form controls.
'use strict';
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');

function control(name, value = '', options = {}) {
    return {name, value, type: 'text', required: false, dataset: {}, files: [], accept: '',
        validityMessage: '', setCustomValidity(message) {this.validityMessage = message;}, ...options};
}
function form(controls, dataset = {}) {
    const listeners = {};
    controls.namedItem = name => controls.find(item => item.name === name);
    return {elements: controls, dataset, addEventListener(name, listener) {listeners[name] = listener;},
        reportValidity() {return controls.every(item => !item.validityMessage);},
        update() {listeners.input();}, submit() {let prevented = false; listeners.submit({preventDefault() {prevented = true;}}); return prevented;}};
}
const role = control('role_id', '3', {selectedOptions: [{textContent: 'STUDENT'}]});
const office = control('office_id', '');
const department = control('department_id', '');
const programme = control('programme', '');
const cycle = control('academic_year', '');
const username = control('username', 'IRDP/ODICT/MA26/9001');
const person = control('full_name', 'Anne O’Neil-Said', {dataset: {personName: 'true'}, required: true});
const account = form([role, office, department, programme, cycle, username, person], {departmentOfficeId: '7'});
const amount = control('amount', '0', {type: 'number', required: true, dataset: {clearanceAmount: 'true'}});
const money = form([amount]);
const picture = control('picture', '', {type: 'file', accept: '.jpg,.jpeg,.png,image/jpeg,image/png'});
const evidence = control('evidence[]', '', {type: 'file', accept: '.pdf,.jpg,.jpeg,.png'});
const importFile = control('file', '', {type: 'file', accept: '.csv,.xlsx'});
const uploads = form([picture, evidence, importFile]);
const type = control('type', 'weekly');
const from = control('from', '');
const to = control('to', '');
const reports = form([type, from, to]);
const mode = control('mode', 'MANUAL_OPEN');
const newCycle = control('new_cycle', '');
const selectedCycle = control('cycle_id', '1');
const opens = control('opens_at', '');
const closes = control('closes_at', '');
const periods = form([mode, newCycle, selectedCycle, opens, closes]);
const password = control('password', 'Garden ocean lantern 82', {type: 'password', dataset: {passwordMin: '8', passwordMax: '64'}});
const confirm = control('confirm_password', password.value, {type: 'password', dataset: {passwordMin: '8', passwordMax: '64'}});
const passwords = form([password, confirm]);

vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../assets/js/validation.js'), 'utf8'),
    {document: {querySelectorAll: () => [account, money, uploads, reports, periods, passwords]}});

assert.equal(department.required, true);
assert.equal(programme.required, true);
assert.equal(office.required, false);
role.selectedOptions[0].textContent = 'OFFICER'; office.value = '7'; account.update();
assert.equal(department.required, true);
assert.equal(programme.required, false);
assert.equal(office.required, true);
office.value = '1'; account.update(); assert.equal(department.required, false);
role.selectedOptions[0].textContent = 'SUPERVISOR'; office.value = '7'; account.update();
assert.equal(department.required, true);
person.value = 'Anne <script>'; account.update(); assert.notEqual(person.validityMessage, '');
person.value = "Anne O'Neil-Said"; account.update(); assert.equal(person.validityMessage, '');
role.selectedOptions[0].textContent = 'STUDENT'; username.value = 'wrong'; account.update();
assert.notEqual(username.validityMessage, '');

for (const bad of ['-1', '1e3', '1.234', '1000000000000']) {
    amount.value = bad; money.update(); assert.notEqual(amount.validityMessage, '');
}
for (const good of ['0', '12.50', '999999999999.99']) {
    amount.value = good; money.update(); assert.equal(amount.validityMessage, '');
}
picture.files = [{name: 'portrait.jpeg', type: 'image/jpeg', size: 5242880}];
uploads.update(); assert.equal(picture.validityMessage, '');
picture.files = [{name: 'portrait.gif', type: 'image/jpeg', size: 100}];
uploads.update(); assert.notEqual(picture.validityMessage, '');
picture.files = [{name: 'portrait.png', type: 'image/png', size: 0}];
uploads.update(); assert.notEqual(picture.validityMessage, '');
evidence.files = Array(6).fill({name: 'receipt.pdf', type: 'application/pdf', size: 100});
uploads.update(); assert.notEqual(evidence.validityMessage, '');
importFile.files = [{name: 'students.csv', type: 'text/csv', size: 5242881}];
uploads.update(); assert.notEqual(importFile.validityMessage, '');

assert.equal(from.required, false);
type.value = 'custom'; reports.update(); assert.equal(from.required, true); assert.equal(to.required, true);
from.value = '2026-10-07'; to.value = '2026-10-06'; reports.update(); assert.notEqual(to.validityMessage, '');
to.value = '2026-10-07'; reports.update(); assert.equal(to.validityMessage, '');
newCycle.value = '2026/2028'; periods.update(); assert.notEqual(newCycle.validityMessage, '');
newCycle.value = '2026/2027'; mode.value = 'SCHEDULED'; periods.update();
assert.equal(selectedCycle.required, false); assert.equal(opens.required, true); assert.equal(closes.required, true);
opens.value = '2026-10-07T10:00'; closes.value = '2026-10-07T09:00'; periods.update();
assert.notEqual(closes.validityMessage, '');

password.value = '🌳'.repeat(63) + '🌊'; confirm.value = password.value; passwords.update();
assert.equal(password.validityMessage, ''); assert.equal(confirm.validityMessage, '');
confirm.value = 'Different garden 82'; passwords.update(); assert.notEqual(confirm.validityMessage, '');
assert.equal(passwords.submit(), true);
console.log('PASS: browser roles/departments, money, uploads, report/period dates and Unicode password confirmation');
