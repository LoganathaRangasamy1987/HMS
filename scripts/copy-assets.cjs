const fs = require('node:fs');
const path = require('node:path');
const destination = path.join(__dirname, '..', 'public', 'vendor', 'bootstrap');
fs.mkdirSync(destination, { recursive: true });
for (const [source, target] of [
  ['bootstrap/dist/css/bootstrap.min.css', 'bootstrap.min.css'],
  ['bootstrap/dist/js/bootstrap.bundle.min.js', 'bootstrap.bundle.min.js'],
  ['bootstrap/LICENSE', 'LICENSE'],
]) {
  fs.copyFileSync(require.resolve(source), path.join(destination, target));
}
console.log('Bootstrap assets copied to public/vendor/bootstrap.');
