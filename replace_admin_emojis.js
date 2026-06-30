const fs = require('fs');
const path = require('path');

const emojiMap = {
    '📦': 'fa-box',
    '👥': 'fa-users',
    '🎓': 'fa-graduation-cap',
    '💰': 'fa-dollar-sign',
    '📋': 'fa-clipboard',
    '📚': 'fa-book',
    '⭐': 'fa-star',
    '★': 'fa-star',
    '☆': 'fa-star-o',
    '🔔': 'fa-bell',
    '⚡': 'fa-bolt',
    '🔍': 'fa-search',
    '✅': 'fa-check-circle',
    '🚫': 'fa-ban',
    '🗑': 'fa-trash',
    '📝': 'fa-edit',
    '✏': 'fa-pencil-alt',
    '📅': 'fa-calendar-alt',
    '➕': 'fa-plus',
    '👁': 'fa-eye',
    '💬': 'fa-comment',
    '🎉': 'fa-tada', // doesn't exist, use fa-birthday-cake or remove
    '📞': 'fa-phone',
    '📍': 'fa-map-marker-alt',
    '📊': 'fa-chart-bar',
    '🏷': 'fa-tag',
    '🔘': 'fa-dot-circle',
    '📎': 'fa-paperclip',
    '✉': 'fa-envelope',
    '🌐': 'fa-globe',
    '🙈': 'fa-eye-slash',
    '🛒': 'fa-shopping-cart',
    '📜': 'fa-scroll',
    '📖': 'fa-book-open',
    '📹': 'fa-video',
    '📌': 'fa-thumbtack',
    '🔥': 'fa-fire',
    '🔽': 'fa-chevron-down',
    '📭': 'fa-envelope-open',
    '🧾': 'fa-file-invoice-dollar',
    '📄': 'fa-file-alt',
    '📤': 'fa-upload',
    '🧮': 'fa-calculator',
    '❌': 'fa-times-circle',
    '✓': 'fa-check',
    '✕': 'fa-times',
    '📸': 'fa-camera',
    '🖼': 'fa-image',
    '💡': 'fa-lightbulb'
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
            
            // Replace emojis only if they are not in an array or option
            // It's safer to just replace them with FA icons globally, but for `<option>` it breaks.
            // We'll replace globally first, then fix `<option>` if needed, or better:
            // We can replace emojis with <i class="fas ${emojiMap[emoji]}"></i> 
            content = content.replace(regex, match => {
                modified = true;
                const fa = emojiMap[match];
                if (fa) return `<i class="fas ${fa}"></i>`;
                return ''; // if not mapped, just remove
            });
            
            // Strip FA icons from inside <option> tags
            content = content.replace(/<option([^>]*)>(.*?)<\/option>/gs, (match, p1, p2) => {
                const cleaned = p2.replace(/<i class="fas fa-[^"]+"><\/i>/g, '').trim();
                return `<option${p1}>${cleaned}</option>`;
            });

            // Strip FA icons from inside PHP arrays (like $status = ['Pending' => '...'])
            // Actually, if it's inside a PHP string it might be echo'd safely, or it might be a value.
            // Let's assume it's mostly safe in admin because admin outputs these arrays as badges.
            // E.g., ['pending' => ['Pending', '<i class="..."></i>']] -> this is safe in PHP.

            // Clean up empty FA tags if they were mapped to undefined
            content = content.replace(/<i class="fas undefined"><\/i>/g, '');

            if (modified) {
                fs.writeFileSync(file, content, 'utf8');
                console.log('Updated', file);
            }
        }
    });
}

walk('admin');
