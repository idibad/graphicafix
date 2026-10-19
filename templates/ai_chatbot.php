<!-- Graphicafix AI Assistant Floating Chatbot Widget (Trained & Brand-Styled) -->
<style>
/* --- Graphicafix AI Chatbot Brand Styles --- */
:root {
    --gfix-primary: #024442;      /* Graphicafix Deep Teal */
    --gfix-primary-dark: #012e2c; /* Darker Teal for gradients */
    --gfix-accent: #B6F763;       /* Graphicafix Vibrant Lime Green */
    --gfix-light: #f0ffff;        /* Graphicafix Ice Light */
    --gfix-dark: #171717;         /* Graphicafix Dark Charcoal */
    --gfix-bg-window: #ffffff;
    --gfix-border: #e5e7eb;
}

/* Floating Chat Button */
#gfix-chatbot-trigger {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 65px;
    height: 65px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--gfix-primary), var(--gfix-primary-dark));
    box-shadow: 0 8px 25px rgba(2, 68, 66, 0.35);
    display: flex;
    justify-content: center;
    align-items: center;
    cursor: pointer;
    z-index: 99999;
    border: 3px solid var(--gfix-accent);
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

#gfix-chatbot-trigger:hover {
    transform: scale(1.1) rotate(5deg);
    background: var(--gfix-accent);
    box-shadow: 0 12px 35px rgba(182, 247, 99, 0.5);
}

#gfix-chatbot-trigger i {
    color: #ffffff;
    font-size: 28px;
    transition: all 0.3s ease;
}

#gfix-chatbot-trigger:hover i {
    color: var(--gfix-primary);
}

#gfix-chatbot-trigger.active i {
    transform: rotate(360deg);
}

/* Notification Badge */
.gfix-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: var(--gfix-accent);
    color: var(--gfix-primary);
    font-size: 11px;
    font-weight: 800;
    padding: 3px 8px;
    border-radius: 20px;
    border: 2px solid var(--gfix-primary);
    animation: gfixBounceBadge 2s infinite;
}

/* Chat Window Container */
#gfix-chatbot-window {
    position: fixed;
    bottom: 110px;
    right: 30px;
    width: 400px;
    max-width: calc(100vw - 40px);
    height: 600px;
    max-height: calc(100vh - 130px);
    background: var(--gfix-bg-window);
    border: 1px solid var(--gfix-border);
    border-radius: 20px;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2), 0 0 0 2px rgba(2, 68, 66, 0.1);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    z-index: 99998;
    opacity: 0;
    transform: translateY(20px) scale(0.95);
    pointer-events: none;
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

#gfix-chatbot-window.open {
    opacity: 1;
    transform: translateY(0) scale(1);
    pointer-events: auto;
}

/* Header */
.gfix-chat-header {
    background: linear-gradient(135deg, var(--gfix-primary), var(--gfix-primary-dark));
    border-bottom: 3px solid var(--gfix-accent);
    padding: 18px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.gfix-header-info {
    display: flex;
    align-items: center;
    gap: 14px;
}

.gfix-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: var(--gfix-accent);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gfix-primary);
    font-size: 22px;
    position: relative;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.gfix-status-dot {
    position: absolute;
    bottom: 2px;
    right: 2px;
    width: 12px;
    height: 12px;
    background: #10b981;
    border-radius: 50%;
    border: 2px solid var(--gfix-primary);
}

.gfix-title h4 {
    margin: 0;
    color: #ffffff;
    font-size: 17px;
    font-weight: 700;
    letter-spacing: 0.3px;
    font-family: 'graphica', Arial, sans-serif;
}

.gfix-title span {
    color: var(--gfix-light);
    font-size: 12px;
    display: flex;
    align-items: center;
    gap: 5px;
    opacity: 0.9;
}

.gfix-close-btn {
    background: rgba(255, 255, 255, 0.1);
    border: none;
    color: #ffffff;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.gfix-close-btn:hover {
    background: var(--gfix-accent);
    color: var(--gfix-primary);
    transform: rotate(90deg);
}

/* Messages Area */
.gfix-chat-body {
    flex: 1;
    padding: 20px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 16px;
    background: #f9fafb;
    scroll-behavior: smooth;
}

.gfix-chat-body::-webkit-scrollbar {
    width: 6px;
}
.gfix-chat-body::-webkit-scrollbar-thumb {
    background: var(--gfix-primary);
    border-radius: 10px;
}

/* Message Bubbles */
.gfix-msg {
    max-width: 88%;
    display: flex;
    flex-direction: column;
    gap: 4px;
    animation: gfixFadeIn 0.3s ease;
}

.gfix-msg.bot {
    align-self: flex-start;
}

.gfix-msg.user {
    align-self: flex-end;
}

.gfix-msg-bubble {
    padding: 14px 18px;
    border-radius: 18px;
    font-size: 14px;
    line-height: 1.6;
    word-wrap: break-word;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

.gfix-msg.bot .gfix-msg-bubble {
    background: #ffffff;
    color: var(--gfix-dark);
    border-bottom-left-radius: 4px;
    border: 1px solid var(--gfix-border);
    border-left: 4px solid var(--gfix-primary);
}

.gfix-msg.user .gfix-msg-bubble {
    background: var(--gfix-primary);
    color: #ffffff;
    border-bottom-right-radius: 4px;
}

.gfix-msg-time {
    font-size: 10px;
    color: #6b7280;
    margin-left: 6px;
}

.gfix-msg.user .gfix-msg-time {
    align-self: flex-end;
    margin-right: 6px;
}

/* Interactive Quick Pills */
.gfix-quick-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 12px;
}

.gfix-pill {
    background: var(--gfix-light);
    border: 1px solid #c8e6e5;
    color: var(--gfix-primary);
    padding: 7px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 6px;
}

.gfix-pill i {
    color: var(--gfix-primary);
}

.gfix-pill:hover {
    background: var(--gfix-primary);
    color: #ffffff;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(2, 68, 66, 0.2);
    border-color: var(--gfix-primary);
}

.gfix-pill:hover i {
    color: var(--gfix-accent);
}

/* Typing Indicator */
.gfix-typing {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 16px 20px !important;
    width: fit-content;
}

.gfix-dot {
    width: 7px;
    height: 7px;
    background: var(--gfix-primary);
    border-radius: 50%;
    animation: gfixTypingBounce 1.4s infinite ease-in-out both;
}

.gfix-dot:nth-child(1) { animation-delay: -0.32s; }
.gfix-dot:nth-child(2) { animation-delay: -0.16s; }

/* Footer Input */
.gfix-chat-footer {
    padding: 16px;
    background: #ffffff;
    border-top: 1px solid var(--gfix-border);
}

.gfix-input-form {
    display: flex;
    align-items: center;
    background: #f3f4f6;
    border: 2px solid transparent;
    border-radius: 30px;
    padding: 4px 6px 4px 18px;
    transition: all 0.3s ease;
}

.gfix-input-form:focus-within {
    border-color: var(--gfix-primary);
    background: #ffffff;
    box-shadow: 0 0 15px rgba(2, 68, 66, 0.1);
}

#gfix-chat-input {
    flex: 1;
    background: transparent;
    border: none;
    color: var(--gfix-dark);
    font-size: 14px;
    outline: none;
    padding: 8px 0;
}

#gfix-chat-input::placeholder {
    color: #9ca3af;
}

.gfix-send-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--gfix-primary);
    border: none;
    color: var(--gfix-accent);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 16px;
    transition: all 0.2s ease;
}

.gfix-send-btn:hover {
    background: var(--gfix-accent);
    color: var(--gfix-primary);
    transform: scale(1.08);
}

/* Animations */
@keyframes gfixBounceBadge {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.15); }
}

@keyframes gfixFadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes gfixTypingBounce {
    0%, 80%, 100% { transform: scale(0); }
    40% { transform: scale(1); }
}

/* Mobile Responsiveness */
@media (max-width: 480px) {
    #gfix-chatbot-window {
        bottom: 90px;
        right: 15px;
        left: 15px;
        width: auto;
        height: 520px;
    }
    #gfix-chatbot-trigger {
        bottom: 20px;
        right: 20px;
        width: 55px;
        height: 55px;
    }
}
</style>

<!-- Floating Trigger Button -->
<div id="gfix-chatbot-trigger" onclick="toggleGfixChat()" title="Chat with Graphicafix AI">
    <i class="fas fa-robot"></i>
    <span class="gfix-badge" id="gfix-chat-badge">1</span>
</div>

<!-- Chat Window -->
<div id="gfix-chatbot-window">
    <!-- Header -->
    <div class="gfix-chat-header">
        <div class="gfix-header-info">
            <div class="gfix-avatar">
                <i class="fas fa-bolt"></i>
                <div class="gfix-status-dot"></div>
            </div>
            <div class="gfix-title">
                <h4>Graphicafix Assistant</h4>
                <span><i class="fas fa-circle" style="color: #10b981; font-size: 8px;"></i> Online & Trained on Graphicafix</span>
            </div>
        </div>
        <button class="gfix-close-btn" onclick="toggleGfixChat()" aria-label="Close Chat">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- Messages Body -->
    <div class="gfix-chat-body" id="gfix-chat-body">
        <!-- Initial Bot Greeting -->
        <div class="gfix-msg bot">
            <div class="gfix-msg-bubble">
                👋 <strong>Hello! Welcome to Graphicafix!</strong><br>
                I am your specialized agency & LMS assistant. I'm trained on our branding, UI/UX, graphic design services, courses, and pricing.<br><br>
                How can we help bring your creative vision to life today?
                <div class="gfix-quick-pills">
                    <div class="gfix-pill" onclick="sendGfixPill('What creative services do you offer?')"><i class="fas fa-paint-brush"></i> Creative Services</div>
                    <div class="gfix-pill" onclick="sendGfixPill('Tell me about your Web & UI/UX Design')"><i class="fas fa-laptop-code"></i> Web & UI/UX Design</div>
                    <div class="gfix-pill" onclick="sendGfixPill('How do your LMS courses and training work?')"><i class="fas fa-graduation-cap"></i> Courses & LMS</div>
                    <div class="gfix-pill" onclick="sendGfixPill('How much do design projects cost?')"><i class="fas fa-tags"></i> Pricing & Quotes</div>
                    <div class="gfix-pill" onclick="sendGfixPill('How do I contact live human support on WhatsApp?')"><i class="fab fa-whatsapp"></i> WhatsApp Support</div>
                </div>
            </div>
            <span class="gfix-msg-time">Just now</span>
        </div>
    </div>

    <!-- Footer Input -->
    <div class="gfix-chat-footer">
        <form class="gfix-input-form" id="gfix-chat-form" onsubmit="handleGfixSubmit(event)">
            <input type="text" id="gfix-chat-input" placeholder="Ask about services, courses, pricing, or support..." autocomplete="off">
            <button type="submit" class="gfix-send-btn" aria-label="Send Message" title="Send Message">
                <i class="fas fa-paper-plane"></i>
            </button>
        </form>
    </div>
</div>

<!-- Chatbot JavaScript Logic (Smart Trained Engine) -->
<script>
let gfixChatOpen = false;

function toggleGfixChat() {
    const windowEl = document.getElementById('gfix-chatbot-window');
    const triggerEl = document.getElementById('gfix-chatbot-trigger');
    const badgeEl = document.getElementById('gfix-chat-badge');

    gfixChatOpen = !gfixChatOpen;
    if (gfixChatOpen) {
        windowEl.classList.add('open');
        triggerEl.classList.add('active');
        if (badgeEl) badgeEl.style.display = 'none';
        setTimeout(() => {
            document.getElementById('gfix-chat-input').focus();
        }, 300);
    } else {
        windowEl.classList.remove('open');
        triggerEl.classList.remove('active');
    }
}

function sendGfixPill(text) {
    appendGfixMessage(text, 'user');
    respondGfixAI(text);
}

function handleGfixSubmit(e) {
    e.preventDefault();
    const inputEl = document.getElementById('gfix-chat-input');
    const text = inputEl.value.trim();
    if (!text) return;

    inputEl.value = '';
    appendGfixMessage(text, 'user');
    respondGfixAI(text);
}

function appendGfixMessage(text, sender) {
    const bodyEl = document.getElementById('gfix-chat-body');
    const msgDiv = document.createElement('div');
    msgDiv.className = `gfix-msg ${sender}`;
    
    const timeStr = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    
    msgDiv.innerHTML = `
        <div class="gfix-msg-bubble">${text}</div>
        <span class="gfix-msg-time">${timeStr}</span>
    `;
    
    bodyEl.appendChild(msgDiv);
    bodyEl.scrollTop = bodyEl.scrollHeight;
}

function showGfixTyping() {
    const bodyEl = document.getElementById('gfix-chat-body');
    const typingDiv = document.createElement('div');
    typingDiv.className = 'gfix-msg bot';
    typingDiv.id = 'gfix-typing-indicator';
    typingDiv.innerHTML = `
        <div class="gfix-msg-bubble gfix-typing">
            <div class="gfix-dot"></div>
            <div class="gfix-dot"></div>
            <div class="gfix-dot"></div>
        </div>
    `;
    bodyEl.appendChild(typingDiv);
    bodyEl.scrollTop = bodyEl.scrollHeight;
}

function hideGfixTyping() {
    const typingEl = document.getElementById('gfix-typing-indicator');
    if (typingEl) typingEl.remove();
}

/**
 * Smart Graphicafix Knowledge Base & Intent Engine
 */
function respondGfixAI(userText) {
    showGfixTyping();
    const query = userText.toLowerCase();

    setTimeout(() => {
        hideGfixTyping();
        let reply = "";

        // 1. Branding & Identity
        if (query.includes('brand') || query.includes('logo') || query.includes('identity') || query.includes('rebrand') || query.includes('guideline') || query.includes('visiting card') || query.includes('letterhead')) {
            reply = `🎨 <strong>Branding & Visual Identity:</strong><br><br>
            At Graphicafix, we build memorable, iconic brands that stand out! Our branding services include:<br>
            • <strong>Custom Logo Design:</strong> Modern, timeless vector logos.<br>
            • <strong>Brand Style Guides:</strong> Color palettes, typography, and usage rules.<br>
            • <strong>Business Collateral:</strong> Visiting cards, letterheads, envelopes, and social kits.<br><br>
            👉 <a href="services.php#branding" style="color: var(--gfix-primary); font-weight: bold; text-decoration: underline;">Explore Branding Packages</a> or <a href="portfolio.php" style="color: var(--gfix-primary); font-weight: bold; text-decoration: underline;">View Our Work</a>!`;
        }
        // 2. Web Design, UI/UX & Development
        else if (query.includes('web') || query.includes('ui') || query.includes('ux') || query.includes('develop') || query.includes('site') || query.includes('app') || query.includes('store') || query.includes('ecommerce') || query.includes('landing page') || query.includes('wordpress') || query.includes('domain')) {
            reply = `💻 <strong>UI/UX & Custom Web Design:</strong><br><br>
            We design and develop lightning-fast, responsive websites and web applications tailored to your business goals:<br>
            • <strong>UI/UX Prototyping:</strong> Wireframes & interactive Figma designs.<br>
            • <strong>Custom Web Development:</strong> High-performance HTML/CSS/PHP & modern frameworks.<br>
            • <strong>E-commerce Stores:</strong> Conversion-optimized online shops.<br>
            • <strong>Responsive Landing Pages:</strong> Designed for high ROI & lead generation.<br><br>
            👉 <a href="services.php#web-design" style="color: var(--gfix-primary); font-weight: bold; text-decoration: underline;">Learn More About Web Design</a>`;
        }
        // 3. Graphic Design & Print
        else if (query.includes('graphic') || query.includes('print') || query.includes('package') || query.includes('packaging') || query.includes('brochure') || query.includes('flyer') || query.includes('banner') || query.includes('poster') || query.includes('illustration') || query.includes('vector') || query.includes('photoshop')) {
            reply = `🖌️ <strong>Graphic & Print Design Solutions:</strong><br><br>
            From digital assets to physical print collateral, our design team delivers eye-catching visuals:<br>
            • <strong>Marketing Materials:</strong> Brochures, flyers, catalogs, and banners.<br>
            • <strong>Product Packaging:</strong> Box designs, labels, and 3D mockups.<br>
            • <strong>Custom Illustrations:</strong> Vector art and promotional graphics.<br><br>
            👉 <a href="services.php#graphic-design" style="color: var(--gfix-primary); font-weight: bold; text-decoration: underline;">Check Out Graphic Design Services</a>`;
        }
        // 4. Social Media Management
        else if (query.includes('social') || query.includes('instagram') || query.includes('facebook') || query.includes('linkedin') || query.includes('tiktok') || query.includes('youtube') || query.includes('reels') || query.includes('marketing') || query.includes('ads') || query.includes('campaign') || query.includes('content') || query.includes('growth')) {
            reply = `📱 <strong>Social Media Growth & Creative Marketing:</strong><br><br>
            Elevate your digital presence with our social media creative direction:<br>
            • <strong>High-Impact Graphics:</strong> Custom posts, stories, and carousel designs.<br>
            • <strong>Ad Campaign Creatives:</strong> Eye-catching visuals optimized for conversions.<br>
            • <strong>Content Strategy:</strong> Consistent visual branding across all platforms.<br><br>
            👉 <a href="services.php#social-media" style="color: var(--gfix-primary); font-weight: bold; text-decoration: underline;">Boost Your Social Media</a>`;
        }
        // 5. LMS, Courses & Training
        else if (query.includes('course') || query.includes('learn') || query.includes('training') || query.includes('student') || query.includes('teacher') || query.includes('certificate') || query.includes('lecture') || query.includes('quiz') || query.includes('class') || query.includes('webinar') || query.includes('education') || query.includes('lms') || query.includes('academy')) {
            reply = `🎓 <strong>Graphicafix Academy & LMS:</strong><br><br>
            We don't just design; we teach! Our platform features a full Learning Management System:<br>
            • <strong>Professional Courses:</strong> Learn graphic design, UI/UX, and web development.<br>
            • <strong>Interactive Learning:</strong> Video lectures, assignments, and quizzes.<br>
            • <strong>Live Classes & Webinars:</strong> Direct mentorship from industry experts.<br>
            • <strong>Verified Certificates:</strong> Earn recognized completion certificates!<br><br>
            👉 <a href="courses/" style="color: var(--gfix-primary); font-weight: bold; text-decoration: underline;">Browse Available Courses</a> or <a href="portal/" style="color: var(--gfix-primary); font-weight: bold; text-decoration: underline;">Login to LMS Portal</a>`;
        }
        // 6. Pricing, Quotes & Payments
        else if (query.includes('price') || query.includes('pricing') || query.includes('cost') || query.includes('budget') || query.includes('rate') || query.includes('package') || query.includes('quote') || query.includes('estimate') || query.includes('how much') || query.includes('discount') || query.includes('cheap') || query.includes('bill') || query.includes('invoice') || query.includes('pay') || query.includes('safepay') || query.includes('jazzcash')) {
            reply = `💰 <strong>Transparent Pricing & Custom Quotes:</strong><br><br>
            At Graphicafix, we tailor our pricing packages to match your exact project scope, timeline, and budget. Whether you need a single logo or an entire design pipeline, we have flexible options!<br><br>
            💳 <strong>Payment Options:</strong> We accept secure online payments via SafePay, JazzCash, and Bank Transfer.<br><br>
            ⚡ <strong>Want an instant quote for your project?</strong><br>
            👉 <a href="contact_us.php" style="color: var(--gfix-primary); font-weight: bold; text-decoration: underline;">Request a Free Custom Quote</a> or chat with us right now on <a href="https://wa.me/923454568986" target="_blank" style="color: #10b981; font-weight: bold;">WhatsApp (+92 345 4568986)</a>!`;
        }
        // 7. Workflow & Process
        else if (query.includes('start') || query.includes('process') || query.includes('workflow') || query.includes('timeline') || query.includes('turnaround') || query.includes('how long') || query.includes('deliver') || query.includes('order') || query.includes('hire') || query.includes('step')) {
            reply = `🚀 <strong>Our 3-Step Creative Workflow:</strong><br><br>
            1️⃣ <strong>Discovery & Strategy:</strong> We discuss your vision, goals, and target audience.<br>
            2️⃣ <strong>Concept & Design:</strong> Our team crafts tailored mockups with rapid iterations.<br>
            3️⃣ <strong>Final Delivery & Support:</strong> Pixel-perfect delivery, source files, and Daily Progress Reports (DPR)!<br><br>
            👉 <a href="contact_us.php" style="color: var(--gfix-primary); font-weight: bold; text-decoration: underline;">Start Your Project Today</a>`;
        }
        // 8. Client Portal & DPR
        else if (query.includes('portal') || query.includes('dpr') || query.includes('progress') || query.includes('tracking') || query.includes('login') || query.includes('account') || query.includes('milestone') || query.includes('crm') || query.includes('report') || query.includes('client')) {
            reply = `🔐 <strong>Graphicafix Client Portal & CRM:</strong><br><br>
            We provide a dedicated portal where clients can:<br>
            • Track project milestones in real-time.<br>
            • View Daily Progress Reports (DPR).<br>
            • Review, approve, and download invoices and quotations.<br>
            • Communicate directly with assigned project managers.<br><br>
            👉 <a href="portal/" style="color: var(--gfix-primary); font-weight: bold; text-decoration: underline;">Access Client Portal</a>`;
        }
        // 9. Career & Jobs
        else if (query.includes('career') || query.includes('job') || query.includes('internship') || query.includes('apply') || query.includes('work with us') || query.includes('vacancy') || query.includes('hiring') || query.includes('resume') || query.includes('cv')) {
            reply = `🌟 <strong>Join the Graphicafix Team!</strong><br><br>
            We are always looking for passionate designers, web developers, and creative thinkers to join our agency in Islamabad or remotely.<br><br>
            👉 <a href="career.php" style="color: var(--gfix-primary); font-weight: bold; text-decoration: underline;">View Open Positions & Apply</a>`;
        }
        // 10. About Us & Location
        else if (query.includes('about') || query.includes('team') || query.includes('who') || query.includes('location') || query.includes('islamabad') || query.includes('experience') || query.includes('why choose') || query.includes('company') || query.includes('agency')) {
            reply = `🏢 <strong>About Graphicafix:</strong><br><br>
            Graphicafix is a premier digital agency and design institute based in <strong>Islamabad, Pakistan</strong>. With diverse skill sets across hundreds of branding, UI/UX, and development projects, we take on creative challenges of any scale!<br><br>
            • <strong>Our Mission:</strong> Combining top-tier agency excellence with world-class design education.<br><br>
            👉 <a href="about.php" style="color: var(--gfix-primary); font-weight: bold; text-decoration: underline;">Read Our Full Story</a>`;
        }
        // 11. Contact & Human Support
        else if (query.includes('contact') || query.includes('human') || query.includes('support') || query.includes('whatsapp') || query.includes('phone') || query.includes('call') || query.includes('email') || query.includes('help') || query.includes('talk') || query.includes('speak') || query.includes('representative') || query.includes('address')) {
            reply = `📞 <strong>Connect with Live Human Support:</strong><br><br>
            Our senior designers and support team are ready to assist you directly!<br><br>
            💬 <strong>WhatsApp Live Chat:</strong> <a href="https://wa.me/923454568986" target="_blank" style="color: #10b981; font-weight: bold;">+92 345 4568986</a><br>
            📧 <strong>Email Support:</strong> <a href="mailto:contact@graphicafix.com" style="color: var(--gfix-primary); font-weight: bold;">contact@graphicafix.com</a><br>
            📍 <strong>Office Location:</strong> Islamabad, Pakistan<br><br>
            <em>We typically respond on WhatsApp within minutes!</em>`;
        }
        // 12. Portfolio & Samples
        else if (query.includes('portfolio') || query.includes('sample') || query.includes('example') || query.includes('past work') || query.includes('case study') || query.includes('showcase') || query.includes('previous')) {
            reply = `🌟 <strong>See Our Craft in Action!</strong><br><br>
            We have delivered hundreds of successful projects across branding, web design, print collateral, and social media campaigns for global clients.<br><br>
            👉 <a href="portfolio.php" style="color: var(--gfix-primary); font-weight: bold; text-decoration: underline;">Explore Our Portfolio</a>`;
        }
        // 13. Greetings
        else if (query.includes('hello') || query.includes('hi') || query.includes('hey') || query.includes('good morning') || query.includes('good afternoon') || query.includes('good evening') || query.includes('greetings') || query.includes('how are you')) {
            reply = `👋 <strong>Hi there! Welcome to Graphicafix!</strong><br><br>
            I am your specialized agency assistant. Feel free to ask me anything about:<br>
            • 🎨 <strong>Branding & Logo Design</strong><br>
            • 💻 <strong>Web & UI/UX Development</strong><br>
            • 🎓 <strong>LMS Courses & Training</strong><br>
            • 💰 <strong>Pricing & Quotations</strong><br><br>
            How can we help your business grow today?`;
        }
        // 14. Intelligent Catch-All / Smart Fallback
        else {
            reply = `✨ <strong>Thank you for your question!</strong><br><br>
            At Graphicafix, we specialize in custom <strong>Branding, Web Design, Graphic Design, Social Media Marketing, and LMS Training Solutions</strong>.<br><br>
            To give you the exact details and guidance for <em>"${userText}"</em>, our senior team would love to chat with you directly!<br><br>
            💬 <a href="https://wa.me/923454568986" target="_blank" style="color: #10b981; font-weight: bold;">Click here to chat on WhatsApp (+92 345 4568986)</a> or <a href="contact_us.php" style="color: var(--gfix-primary); font-weight: bold; text-decoration: underline;">Send us a message</a>!`;
        }

        appendGfixMessage(reply, 'bot');
    }, 700);
}
</script>
