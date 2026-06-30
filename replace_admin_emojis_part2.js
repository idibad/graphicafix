const fs = require('fs');
const path = require('path');

const emojiMap = {
    '🏢': 'fa-building',
    '📢': 'fa-bullhorn',
    '🔴': 'fa-circle text-danger',
    '🟠': 'fa-circle text-warning',
    '🟢': 'fa-circle text-success',
    '🟡': 'fa-circle text-warning',
    '👨': 'fa-user-tie',
    '💼': 'fa-briefcase',
    '📥': 'fa-inbox',
    '⚠': 'fa-exclamation-triangle',
    '🕒': 'fa-clock',
    '🔒': 'fa-lock',
    '🔓': 'fa-unlock',
    '👤': 'fa-user',
    '📁': 'fa-folder',
    '📧': 'fa-envelope',
    '🗓': 'fa-calendar',
    '💾': 'fa-save',
    '🎟': 'fa-ticket-alt',
    '🔐': 'fa-lock',
    '🤝': 'fa-handshake',
    '❄': 'fa-snowflake',
    '🧲': 'fa-magnet',
    '📆': 'fa-calendar-alt',
    '🕐': 'fa-clock',
    '🔗': 'fa-link',
    '🎯': 'fa-bullseye',
    '📩': 'fa-envelope-open-text',
    '📈': 'fa-chart-line',
    '🚀': 'fa-rocket',
    '♻': 'fa-recycle',
    '🛡': 'fa-shield-alt',
    '🛠': 'fa-tools',
    '✍': 'fa-signature'
};

const regex = new RegExp('[' + Object.keys(emojiMap).join('') + ']', 'gu');

function walk(dir) {
    const list = fs.readdirSync(dir);
    list.forEach(file => {
        file = path.join(dir, file);
        const stat = fs.statSync(file);
        if (stat && stat.isDirectory()) {
            walk(file);
        } else if (file.endsWith('.php')) {
            let content = fs.readFileSync(file, 'utf8');
            let modified = false;
            
            content = content.replace(regex, match => {
                modified = true;
                const fa = emojiMap[match];
                if (fa) return `<i class="fas ${fa}"></i>`;
                return ''; 
            });
            
            content = content.replace(/<option([^>]*)>(.*?)<\/option>/gs, (match, p1, p2) => {
                const cleaned = p2.replace(/<i class="fas fa-[^"]+"><\/i>/g, '').trim();
                const cleaned2 = cleaned.replace(/<i class="fas fa-[^"]+ text-[^"]+"><\/i>/g, '').trim();
                return `<option${p1}>${cleaned2}</option>`;
            });

            if (modified) {
                fs.writeFileSync(file, content, 'utf8');
                console.log('Updated', file);
            }
        }
    });
}

walk('admin');
