<?php
/**
 * Cinematic Animated Motion Landing Page for RJM Boardinghouse
 * Inspired by the Prisma aesthetic (warm cream #DEDBC8, deep obsidian #000000, 
 * Almarai + Instrument Serif typography, SVG noise shaders, GSAP pull-up text,
 * scroll-linked character opacity reveal, and GPS Map showcase).
 */

$pageTitle = 'RJM Boardinghouse — Modern Living, Smart Security & Sanctuary';
$sessionRole = $sessionRole ?? null;
$dashboardUrl = $dashboardUrl ?? null;
$userName = $userName ?? null;
$contactInfo = $contactInfo ?? [
    'caretakers'     => 'Ate Mary & Kuya Jun (On-site Caretakers)',
    'primary_phone'  => '+63 917 842 5900',
    'primary_tel'    => 'tel:+639178425900',
    'landline'       => '(083) 228-4012',
    'landline_tel'   => 'tel:+63832284012',
    'inquiry_email'  => 'inquiries@rjmboardinghouse.ph',
    'admin_email'    => 'admin@rjmboardinghouse.ph',
    'facebook_url'   => 'https://facebook.com/rjmboardinghouse',
    'messenger_url'  => 'https://m.me/rjmboardinghouse',
    'address_line1'  => 'Zone 3, Purok Sanctuary',
    'address_line2'  => 'Tupi, South Cotabato, 9505 Philippines',
    'office_hours'   => 'Mon – Sat: 8:00 AM – 6:00 PM | Sun: 1:00 PM – 5:00 PM',
    'curfew_hours'   => '10:00 PM Daily (Smart QR Gate Pass Access)',
    'emergency_line' => '24/7 Security Alarm Dispatch'
];
$mapData = $mapData ?? [
    'latitude'     => 6.340433,
    'longitude'    => 124.935983,
    'short_url'    => 'https://maps.app.goo.gl/r2xxZ1Kap72VGa8M9',
    'display_name' => 'RJM Boardinghouse (MJL Residence)',
    'address'      => 'Zone 3, Tupi, South Cotabato, 9505 Philippines',
    'landmarks'    => [
        ['name' => 'South East Asian Institute of Technology (SEAIT)', 'distance' => '4-6 mins', 'type' => 'College'],
        ['name' => 'Tupi Municipal Hall & Civic Plaza', 'distance' => '3-4 mins', 'type' => 'Civic Center'],
        ['name' => 'Tupi Public Market & Commercial Center', 'distance' => '3 mins', 'type' => 'Market'],
        ['name' => 'General Santos - Marbel National Highway', 'distance' => '2 mins', 'type' => 'Transit'],
    ],
];
$roomTiers = $roomTiers ?? [
    [
        'id'          => 'solo',
        'type'        => 'Solo Executive Room',
        'badge'       => 'Maximum Privacy',
        'popular'     => false,
        'price'       => '₱3,500',
        'period'      => '/ month',
        'capacity'    => '1 Resident (Private)',
        'features'    => [
            'Private furnished room with solid hardwood door',
            'Dedicated ergonomic study desk & reading lamp',
            'Individual steel locker & wardrobe cabinet',
            'Dedicated electric submeter & private ceiling fan',
            'Fiber Wi-Fi & quiet study floor allocation'
        ],
        'cta_label'   => 'Inquire for Solo Room'
    ],
    [
        'id'          => 'twin',
        'type'        => 'Twin Sharing Scholar Suite',
        'badge'       => 'Most Popular',
        'popular'     => true,
        'price'       => '₱2,200',
        'period'      => '/ bed / month',
        'capacity'    => '2 Residents (Shared)',
        'features'    => [
            'Spacious 2-bed layout with dual study stations',
            'Two separate secure steel storage lockers',
            'Wide cross-ventilation windows with blackout curtains',
            'High-speed Wi-Fi & power charging hubs',
            'Split electricity submetering per occupant'
        ],
        'cta_label'   => 'Inquire for Twin Sharing'
    ],
    [
        'id'          => 'quad',
        'type'        => 'Quad Bedspace Sanctuary',
        'badge'       => 'Best Value',
        'popular'     => false,
        'price'       => '₱1,600',
        'period'      => '/ bed / month',
        'capacity'    => '4 Residents (Bunk Bed)',
        'features'    => [
            'Heavy-duty mahogany double-deck bunks with privacy curtains',
            'Individual LED night lamp & dual USB wall sockets',
            'Assigned lockable heavy-duty storage compartment',
            'Complimentary purified drinking water station',
            'Full access to shared study hall & pantry station'
        ],
        'cta_label'   => 'Inquire for Bedspace'
    ]
];
$stats = $stats ?? ['total_rooms' => 0, 'total_beds' => 0, 'vacant_beds' => 0];
$inquirySuccess = $inquirySuccess ?? null;
$inquiryError = $inquiryError ?? null;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars($pageTitle) ?></title>

    <!-- Google Fonts: Almarai & Instrument Serif -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">

    <!-- Vendored GSAP & ScrollTrigger -->
    <script src="/assets/js/vendor/gsap.min.js"></script>
    <script src="/assets/js/vendor/ScrollTrigger.min.js"></script>

    <style>
        /* ================================================================= */
        /* RESET & CORE VARIABLES                                            */
        /* ================================================================= */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --color-cream-primary: #DEDBC8;
            --color-cream-light: #E1E0CC;
            --color-cream-muted: #8E8D82;
            --color-bg-black: #000000;
            --color-surface-about: #101010;
            --color-surface-feature: #181818;
            --color-surface-card: #212121;
            --color-border-subtle: rgba(222, 219, 200, 0.12);
            --color-border-card: rgba(255, 255, 255, 0.08);
            --font-sans: 'Almarai', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --font-serif: 'Instrument Serif', Georgia, serif;
        }

        html {
            scroll-behavior: smooth;
            background-color: #000000;
            color: #E1E0CC;
            font-family: var(--font-sans);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        body {
            background-color: #000000;
            color: #E1E0CC;
            overflow-x: hidden;
            width: 100%;
            min-height: 100vh;
        }

        ::selection {
            background-color: var(--color-cream-primary);
            color: #000000;
        }

        /* SVG Sizing Lock (Prevents oversized icons) */
        svg {
            display: inline-block;
            vertical-align: middle;
            flex-shrink: 0;
        }
        .icon-xs { width: 14px; height: 14px; min-width: 14px; min-height: 14px; }
        .icon-sm { width: 18px; height: 18px; min-width: 18px; min-height: 18px; }
        .icon-md { width: 22px; height: 22px; min-width: 22px; min-height: 22px; }
        .icon-lg { width: 28px; height: 28px; min-width: 28px; min-height: 28px; }

        /* Typography Helper Classes */
        .font-serif { font-family: var(--font-serif); }
        .italic { font-style: italic; }

        /* SVG Noise Texture Shaders via Data URI */
        .noise-overlay {
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E");
        }
        .bg-noise {
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter2'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter2)'/%3E%3C/svg%3E");
        }

        /* Words Pull Up Component */
        .word-wrap {
            display: inline-block;
            overflow: hidden;
            vertical-align: bottom;
            line-height: 1;
        }
        .word-inner {
            display: inline-block;
            transform: translateY(115%);
            opacity: 0;
            will-change: transform, opacity;
        }

        /* Progressive Character Opacity Reveal */
        .char-reveal {
            opacity: 0.2;
            transition: opacity 0.15s ease;
            will-change: opacity;
        }

        /* Radar Pulse Animation */
        @keyframes radar-pulse {
            0% { transform: scale(0.95); opacity: 0.8; }
            50% { transform: scale(1.4); opacity: 0; }
            100% { transform: scale(0.95); opacity: 0; }
        }
        .animate-radar {
            animation: radar-pulse 2s cubic-bezier(0.2, 0.8, 0.2, 1) infinite;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #090909; }
        ::-webkit-scrollbar-thumb { background: #262626; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #3f3f3f; }

        /* ================================================================= */
        /* SECTION 1: HERO LAYOUT                                            */
        /* ================================================================= */
        .hero-section {
            padding: 1rem;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background-color: #000000;
        }
        @media (min-width: 768px) {
            .hero-section { padding: 1.5rem; }
        }

        .hero-container {
            position: relative;
            width: 100%;
            height: calc(100vh - 2rem);
            min-height: 600px;
            max-height: 980px;
            border-radius: 1.75rem;
            overflow: hidden;
            border: 1px solid var(--color-border-subtle);
            background-color: #0b0b0b;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.9);
        }
        @media (min-width: 768px) {
            .hero-container {
                height: calc(100vh - 3rem);
                border-radius: 2.25rem;
            }
        }

        .hero-video-bg {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: brightness(0.68) contrast(1.15);
            z-index: 1;
        }
        .hero-gradient-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(0,0,0,0.65) 0%, rgba(0,0,0,0.1) 40%, rgba(0,0,0,0.95) 100%);
            z-index: 2;
            pointer-events: none;
        }
        .hero-noise-overlay {
            position: absolute;
            inset: 0;
            z-index: 3;
            opacity: 0.65;
            mix-blend-mode: overlay;
            pointer-events: none;
        }

        /* Hanging Pill Navbar */
        .hanging-nav-wrap {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            display: flex;
            justify-content: center;
            padding-top: 0.25rem;
        }
        .hanging-nav {
            background: rgba(10, 10, 10, 0.88);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-top: none;
            border-radius: 0 0 1.5rem 1.5rem;
            padding: 0.75rem 1.75rem;
            display: flex;
            align-items: center;
            gap: 1.75rem;
            box-shadow: 0 16px 36px rgba(0,0,0,0.8);
        }
        @media (min-width: 1024px) {
            .hanging-nav {
                padding: 0.85rem 2.25rem;
                gap: 2.75rem;
                border-radius: 0 0 2rem 2rem;
            }
        }

        .nav-brand {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            color: #E1E0CC;
            font-weight: 800;
            font-size: 0.875rem;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }
        .nav-brand-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background-color: var(--color-cream-primary);
        }
        .nav-links-desktop {
            display: none;
            align-items: center;
            gap: 1.75rem;
        }
        @media (min-width: 640px) {
            .nav-links-desktop { display: flex; }
        }
        .nav-link {
            color: rgba(225, 224, 204, 0.75);
            text-decoration: none;
            font-size: 0.8125rem;
            font-weight: 500;
            transition: color 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        .nav-link:hover { color: #E1E0CC; }

        .btn-nav-login {
            background-color: rgba(255, 255, 255, 0.1);
            color: #E1E0CC;
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.45rem 1rem;
            border-radius: 9999px;
            border: 1px solid rgba(255, 255, 255, 0.18);
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            transition: all 0.2s ease;
        }
        .btn-nav-login:hover {
            background-color: var(--color-cream-primary);
            color: #000000;
            border-color: var(--color-cream-primary);
        }

        /* Hero Content Bottom Grid */
        .hero-bottom-grid {
            position: relative;
            z-index: 10;
            padding: 2rem 1.75rem 2.5rem 1.75rem;
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
            align-items: end;
        }
        @media (min-width: 1024px) {
            .hero-bottom-grid {
                grid-template-columns: 1.85fr 1fr;
                padding: 3.5rem 4rem 4rem 4rem;
                gap: 3rem;
            }
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0.85rem;
            border-radius: 9999px;
            background: rgba(0, 0, 0, 0.55);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            font-size: 0.6875rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--color-cream-primary);
            margin-bottom: 1rem;
        }
        .hero-badge-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background-color: #10B981;
        }

        .hero-giant-title {
            font-size: clamp(3.2rem, 11.5vw, 9.8rem);
            font-weight: 600;
            line-height: 0.86;
            letter-spacing: -0.06em;
            color: #E1E0CC;
            user-select: none;
        }
        .hero-asterisk {
            position: relative;
            top: -0.42em;
            font-family: var(--font-serif);
            font-style: italic;
            color: var(--color-cream-primary);
            font-size: 0.38em;
            margin-left: -0.1em;
        }

        .hero-subtext {
            color: rgba(222, 219, 200, 0.82);
            font-size: 0.9375rem;
            line-height: 1.6;
            max-width: 28rem;
        }
        @media (min-width: 1024px) {
            .hero-subtext { font-size: 1rem; }
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.85rem;
            padding-top: 0.5rem;
        }

        .btn-pill-primary {
            background-color: var(--color-cream-primary);
            color: #000000;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.9375rem;
            padding: 0.5rem 0.65rem 0.5rem 1.65rem;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            gap: 0.85rem;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 12px 28px rgba(0,0,0,0.6);
            border: none;
            cursor: pointer;
        }
        .btn-pill-primary:hover {
            background-color: #eae8d9;
            transform: translateY(-2px);
            gap: 1.15rem;
            box-shadow: 0 16px 36px rgba(0,0,0,0.7);
        }
        .btn-pill-primary .circle-arrow {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 9999px;
            background-color: #000000;
            color: var(--color-cream-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .btn-pill-primary:hover .circle-arrow {
            transform: scale(1.1);
        }

        .btn-pill-secondary {
            background-color: rgba(255, 255, 255, 0.06);
            color: #E1E0CC;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.875rem;
            padding: 0.75rem 1.4rem;
            border-radius: 9999px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(8px);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
        }
        .btn-pill-secondary:hover {
            background-color: rgba(255, 255, 255, 0.12);
            border-color: rgba(255, 255, 255, 0.3);
            color: #FFFFFF;
        }

        /* ================================================================= */
        /* SECTION 2: ABOUT / SANCTUARY                                      */
        /* ================================================================= */
        .about-section {
            padding: 6rem 1.5rem;
            background-color: #000000;
            display: flex;
            justify-content: center;
        }
        @media (min-width: 768px) {
            .about-section { padding: 8rem 2rem; }
        }

        .about-card {
            background-color: var(--color-surface-about);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 2rem;
            padding: 3rem 2rem;
            max-width: 72rem;
            width: 100%;
            position: relative;
            overflow: hidden;
            box-shadow: 0 30px 70px rgba(0,0,0,0.8);
            text-align: center;
        }
        @media (min-width: 768px) {
            .about-card {
                border-radius: 2.5rem;
                padding: 5rem 4rem;
            }
        }

        .section-label {
            display: inline-block;
            font-size: 0.6875rem;
            font-weight: 700;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--color-cream-primary);
            padding: 0.35rem 0.9rem;
            border-radius: 9999px;
            background-color: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
            margin-bottom: 1.75rem;
        }

        .about-heading {
            font-size: clamp(1.85rem, 4.5vw, 3.8rem);
            font-weight: 400;
            line-height: 1.12;
            letter-spacing: -0.03em;
            color: #E1E0CC;
            max-width: 54rem;
            margin: 0 auto 2.5rem auto;
        }

        .about-narrative {
            font-size: clamp(1rem, 1.8vw, 1.35rem);
            line-height: 1.7;
            color: var(--color-cream-primary);
            max-width: 48rem;
            margin: 0 auto;
            user-select: none;
        }

        .metrics-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 2rem;
            margin-top: 4rem;
            padding-top: 3rem;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }
        @media (min-width: 768px) {
            .metrics-row { grid-template-columns: repeat(4, 1fr); }
        }

        .metric-val {
            font-family: var(--font-serif);
            font-style: italic;
            font-size: clamp(2rem, 3.5vw, 2.75rem);
            font-weight: 700;
            color: #E1E0CC;
            line-height: 1;
        }
        .metric-label {
            font-size: 0.6875rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #9CA3AF;
            margin-top: 0.5rem;
        }

        /* ================================================================= */
        /* SECTION 3: BENTO GRID                                             */
        /* ================================================================= */
        .features-section {
            padding: 6rem 1.5rem;
            background-color: #000000;
            position: relative;
        }
        @media (min-width: 768px) {
            .features-section { padding: 8rem 2rem; }
        }

        .features-container {
            max-w: 80rem;
            margin: 0 auto;
            max-width: 80rem;
            position: relative;
            z-index: 5;
        }

        .features-header {
            max-width: 44rem;
            margin-bottom: 3.5rem;
        }
        .features-header h2 {
            font-size: clamp(1.75rem, 4vw, 3.2rem);
            font-weight: 400;
            color: #E1E0CC;
            line-height: 1.15;
            letter-spacing: -0.03em;
            margin-top: 0.5rem;
        }
        .features-header p {
            color: #9CA3AF;
            font-size: 1rem;
            margin-top: 0.75rem;
        }

        .bento-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.25rem;
        }
        @media (min-width: 640px) {
            .bento-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (min-width: 1024px) {
            .bento-grid { grid-template-columns: 1.25fr 1fr 1fr 1fr; }
        }

        .bento-card {
            background-color: var(--color-surface-feature);
            border: 1px solid var(--color-border-card);
            border-radius: 1.5rem;
            padding: 1.75rem;
            min-height: 440px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
            box-shadow: 0 16px 36px rgba(0,0,0,0.5);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .bento-card:hover {
            border-color: rgba(222, 219, 200, 0.3);
            transform: translateY(-4px);
            background-color: #1c1c1c;
            box-shadow: 0 24px 50px rgba(0,0,0,0.7);
        }

        .bento-video-card {
            padding: 1.75rem;
            justify-content: flex-end;
            background-color: #0d0d0d;
        }
        .bento-video-bg {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: brightness(0.65) contrast(1.15);
            transition: transform 0.7s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .bento-video-card:hover .bento-video-bg {
            transform: scale(1.04);
        }
        .bento-video-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(0,0,0,0.2) 0%, rgba(0,0,0,0.4) 40%, rgba(0,0,0,0.95) 100%);
            z-index: 2;
        }

        .card-top-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
        }
        .card-icon-box {
            width: 2.75rem;
            height: 2.75rem;
            border-radius: 0.85rem;
            background-color: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--color-cream-primary);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card-num {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.75rem;
            font-weight: 700;
            color: rgba(222, 219, 200, 0.6);
            letter-spacing: 0.1em;
        }

        .card-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #E1E0CC;
            margin-bottom: 1.25rem;
        }

        .checklist {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
        }
        .checklist-item {
            display: flex;
            align-items: flex-start;
            gap: 0.65rem;
            font-size: 0.8125rem;
            color: #D1D5DB;
            line-height: 1.45;
        }
        .checklist-icon {
            color: var(--color-cream-primary);
            margin-top: 2px;
        }

        .card-footer-link {
            padding-top: 1.25rem;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            display: flex;
            align-items: center;
            gap: 0.45rem;
            text-decoration: none;
            color: var(--color-cream-primary);
            font-size: 0.8125rem;
            font-weight: 600;
            transition: color 0.2s ease;
        }
        .card-footer-link:hover { color: #FFFFFF; }
        .card-footer-link svg {
            transition: transform 0.25s ease;
            transform: rotate(-45deg);
        }
        .card-footer-link:hover svg {
            transform: rotate(-45deg) translate(2px, -2px);
        }

        /* ================================================================= */
        /* SECTION 4: GPS LOCATION SHOWCASE                                  */
        /* ================================================================= */
        .location-section {
            padding: 6rem 1.5rem;
            background-color: #0a0a0a;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
        }
        @media (min-width: 768px) {
            .location-section { padding: 8rem 2rem; }
        }

        .location-container {
            max-width: 80rem;
            margin: 0 auto;
        }

        .location-header {
            text-align: center;
            max-width: 42rem;
            margin: 0 auto 3.5rem auto;
        }
        .location-header h2 {
            font-size: clamp(1.75rem, 4vw, 3.2rem);
            font-weight: 400;
            color: #E1E0CC;
            line-height: 1.15;
            letter-spacing: -0.03em;
            margin-top: 0.5rem;
        }
        .location-header p {
            color: #9CA3AF;
            font-size: 0.9375rem;
            margin-top: 0.75rem;
        }

        .location-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
            align-items: stretch;
        }
        @media (min-width: 1024px) {
            .location-grid { grid-template-columns: 1.45fr 1fr; }
        }

        .map-frame-card {
            background-color: #121212;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1.75rem;
            padding: 0.85rem;
            box-shadow: 0 20px 50px rgba(0,0,0,0.8);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .map-wrapper {
            position: relative;
            width: 100%;
            height: 380px;
            border-radius: 1.25rem;
            overflow: hidden;
            background-color: #1e1e1e;
        }
        @media (min-width: 768px) {
            .map-wrapper { height: 460px; }
        }

        .map-iframe {
            width: 100%;
            height: 100%;
            border: 0;
            filter: invert(90%) hue-rotate(180deg) contrast(1.15);
            opacity: 0.92;
            transition: opacity 0.2s ease;
        }
        .map-iframe:hover { opacity: 1; }

        .floating-gps-pill {
            position: absolute;
            top: 1rem;
            left: 1rem;
            z-index: 10;
            background: rgba(10, 10, 10, 0.88);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            padding: 0.5rem 0.9rem;
            border-radius: 0.75rem;
            font-size: 0.75rem;
            color: #E1E0CC;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 8px 24px rgba(0,0,0,0.7);
        }

        .floating-map-btn {
            position: absolute;
            bottom: 1rem;
            right: 1rem;
            z-index: 10;
            background-color: var(--color-cream-primary);
            color: #000000;
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.65rem 1.1rem;
            border-radius: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.8);
            transition: transform 0.2s ease;
        }
        .floating-map-btn:hover {
            transform: scale(1.05);
            background-color: #eae8d9;
        }

        .map-address-bar {
            padding: 1rem 0.5rem 0.25rem 0.5rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            font-size: 0.75rem;
            color: #9CA3AF;
        }

        .compass-col {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            justify-content: space-between;
        }

        .landmarks-card {
            background-color: #141414;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 1.75rem;
            padding: 1.75rem;
            box-shadow: 0 16px 36px rgba(0,0,0,0.5);
        }
        .landmarks-title-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.25rem;
        }
        .landmarks-title-row h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #E1E0CC;
        }

        .landmark-item {
            padding: 0.85rem 1rem;
            border-radius: 0.85rem;
            background-color: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            transition: border-color 0.2s ease;
        }
        .landmark-item:hover {
            border-color: rgba(255, 255, 255, 0.15);
        }
        .landmark-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .landmark-icon {
            width: 2rem;
            height: 2rem;
            border-radius: 0.5rem;
            background-color: rgba(222, 219, 200, 0.08);
            color: var(--color-cream-primary);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .landmark-name {
            font-size: 0.8125rem;
            font-weight: 600;
            color: #E1E0CC;
        }
        .landmark-type {
            font-size: 0.6875rem;
            color: #9CA3AF;
        }
        .landmark-distance {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--color-cream-primary);
            background: rgba(0, 0, 0, 0.5);
            padding: 0.25rem 0.65rem;
            border-radius: 0.35rem;
            border: 1px solid rgba(255, 255, 255, 0.06);
        }

        .buffer-card {
            background-color: #141414;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 1.5rem;
            padding: 1.5rem;
        }
        .buffer-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: #E1E0CC;
            margin-bottom: 0.5rem;
        }
        .buffer-desc {
            font-size: 0.75rem;
            color: #9CA3AF;
            line-height: 1.6;
        }

        /* ================================================================= */
        /* SECTION 5: GATEWAY & FOOTER                                       */
        /* ================================================================= */
        .gateway-section {
            padding: 5rem 1.5rem 6rem 1.5rem;
            background-color: #000000;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            text-align: center;
        }
        .gateway-container {
            max-width: 48rem;
            margin: 0 auto;
        }
        .gateway-heading {
            font-size: clamp(2rem, 4.5vw, 3.4rem);
            font-weight: 400;
            color: #E1E0CC;
            letter-spacing: -0.03em;
            line-height: 1.15;
            margin-top: 0.5rem;
        }
        .gateway-desc {
            color: #9CA3AF;
            font-size: 1rem;
            margin: 1rem auto 2.5rem auto;
            max-width: 34rem;
            line-height: 1.6;
        }

        .gateway-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 1rem;
        }

        .roles-pills {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            margin-top: 3rem;
            font-size: 0.75rem;
            color: #6B7280;
        }
        .role-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        .role-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
        }

        /* Footer */
        .footer-bar {
            padding: 2rem 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            background-color: #000000;
            font-size: 0.75rem;
            color: #6B7280;
        }
        .footer-inner {
            max-width: 80rem;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            gap: 1.25rem;
        }
        @media (min-width: 640px) {
            .footer-inner {
                flex-direction: row;
            }
        }
        .footer-links {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        .footer-links a {
            color: #6B7280;
            text-decoration: none;
            transition: color 0.2s ease;
        }
        /* ----------------------------------------------------------------- */
        /* MOBILE NAV & DRAWER                                               */
        /* ----------------------------------------------------------------- */
        .btn-mobile-nav {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #E1E0CC;
            border-radius: 9999px;
            padding: 0.5rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-mobile-nav:hover {
            background: rgba(255, 255, 255, 0.12);
            color: #FFFFFF;
        }
        @media (min-width: 768px) {
            .btn-mobile-nav { display: none; }
        }

        .mobile-drawer {
            position: fixed;
            top: 4.5rem;
            left: 1rem;
            right: 1rem;
            max-width: 28rem;
            margin: 0 auto;
            background: rgba(15, 15, 15, 0.96);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 1.5rem;
            padding: 1.25rem 1.5rem;
            z-index: 999;
            box-shadow: 0 24px 50px rgba(0, 0, 0, 0.9);
            opacity: 0;
            pointer-events: none;
            transform: translateY(-10px);
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .mobile-drawer.open {
            opacity: 1;
            pointer-events: auto;
            transform: translateY(0);
        }
        .mobile-drawer-inner {
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
        }
        .mobile-nav-link {
            color: #D1D5DB;
            text-decoration: none;
            font-size: 0.9375rem;
            font-weight: 500;
            padding: 0.5rem 0.25rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: color 0.2s ease;
        }
        .mobile-nav-link:hover {
            color: var(--color-cream-primary);
        }

        /* ----------------------------------------------------------------- */
        /* HERO CONTACT TICKER BAR                                           */
        /* ----------------------------------------------------------------- */
        .hero-contact-ticker {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.75rem 1.25rem;
            margin-top: 1.5rem;
            padding-top: 1.25rem;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            font-size: 0.75rem;
            color: #9CA3AF;
        }
        .ticker-item {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        .ticker-label {
            color: var(--color-cream-muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-size: 0.6875rem;
        }
        .ticker-link {
            color: var(--color-cream-primary);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s ease;
        }
        .ticker-link:hover {
            text-decoration: underline;
            color: #FFFFFF;
        }
        .ticker-sep {
            color: rgba(255, 255, 255, 0.2);
        }

        /* ----------------------------------------------------------------- */
        /* SECTION: ROOMS & RATES                                            */
        /* ----------------------------------------------------------------- */
        .rooms-section {
            padding: 6rem 1.5rem;
            background-color: var(--color-surface-about);
            position: relative;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
        }
        @media (min-width: 768px) {
            .rooms-section { padding: 8rem 2rem; }
        }
        .rooms-container {
            max-width: 80rem;
            margin: 0 auto;
        }
        .rooms-header {
            max-width: 48rem;
            margin-bottom: 3.5rem;
        }
        .rooms-header h2 {
            font-size: clamp(1.75rem, 4vw, 3.2rem);
            font-weight: 400;
            color: #E1E0CC;
            line-height: 1.15;
            letter-spacing: -0.03em;
            margin-top: 0.5rem;
        }
        .rooms-header p {
            color: #9CA3AF;
            font-size: 1rem;
            margin-top: 0.75rem;
            line-height: 1.6;
        }

        .rooms-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }
        @media (min-width: 768px) {
            .rooms-grid { grid-template-columns: repeat(3, 1fr); }
        }

        .room-tier-card {
            background-color: var(--color-surface-card);
            border: 1px solid var(--color-border-card);
            border-radius: 1.5rem;
            padding: 2rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.4);
        }
        .room-tier-card:hover {
            transform: translateY(-4px);
            border-color: rgba(222, 219, 200, 0.35);
            background-color: #242424;
            box-shadow: 0 24px 50px rgba(0, 0, 0, 0.7);
        }
        .room-tier-card.tier-popular {
            border-color: rgba(16, 185, 129, 0.4);
            background-color: #1a1e1b;
        }
        .tier-popular-pill {
            position: absolute;
            top: -0.75rem;
            right: 1.75rem;
            background: #10B981;
            color: #000000;
            font-size: 0.6875rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }

        .tier-top {
            margin-bottom: 1.25rem;
        }
        .tier-badge {
            font-size: 0.6875rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            font-weight: 700;
            color: var(--color-cream-muted);
            margin-bottom: 0.5rem;
            display: inline-block;
        }
        .tier-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #E1E0CC;
            letter-spacing: -0.02em;
            margin-bottom: 0.5rem;
        }
        .tier-capacity {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.8125rem;
            color: #9CA3AF;
        }

        .tier-price-row {
            display: flex;
            align-items: baseline;
            gap: 0.35rem;
            margin: 1.25rem 0 1.5rem 0;
            padding-bottom: 1.25rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        .tier-price {
            font-family: var(--font-serif);
            font-size: 2.5rem;
            font-weight: 700;
            font-style: italic;
            color: #E1E0CC;
            line-height: 1;
        }
        .tier-period {
            font-size: 0.875rem;
            color: #9CA3AF;
        }

        .tier-features {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 2rem;
            flex-grow: 1;
        }
        .tier-feature-item {
            display: flex;
            align-items: flex-start;
            gap: 0.6rem;
            font-size: 0.8125rem;
            color: #D1D5DB;
            line-height: 1.45;
        }

        .btn-tier-inquire {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
            padding: 0.75rem 1.25rem;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 9999px;
            color: #E1E0CC;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .btn-tier-inquire:hover {
            background: var(--color-cream-primary);
            color: #000000;
            border-color: var(--color-cream-primary);
        }
        .room-tier-card.tier-popular .btn-tier-inquire {
            background: #10B981;
            color: #000000;
            border-color: #10B981;
        }
        .room-tier-card.tier-popular .btn-tier-inquire:hover {
            background: #059669;
            color: #FFFFFF;
            border-color: #059669;
        }

        .inclusions-banner {
            margin-top: 3.5rem;
            padding: 1.75rem;
            border-radius: 1.25rem;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.06);
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        @media (min-width: 768px) {
            .inclusions-banner {
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
            }
        }
        .inclusions-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--color-cream-primary);
        }
        .inclusions-items {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem;
        }
        .inc-pill {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.75rem;
            border-radius: 9999px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            font-size: 0.75rem;
            color: #D1D5DB;
        }

        /* ----------------------------------------------------------------- */
        /* SECTION: CONTACT US & DIRECT INQUIRIES                            */
        /* ----------------------------------------------------------------- */
        .contact-section {
            padding: 6rem 1.5rem;
            background-color: #000000;
            position: relative;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
        }
        @media (min-width: 768px) {
            .contact-section { padding: 8rem 2rem; }
        }
        .contact-container {
            max-width: 80rem;
            margin: 0 auto;
        }
        .contact-header {
            max-width: 48rem;
            margin-bottom: 3.5rem;
        }
        .contact-header h2 {
            font-size: clamp(1.75rem, 4vw, 3.2rem);
            font-weight: 400;
            color: #E1E0CC;
            line-height: 1.15;
            letter-spacing: -0.03em;
            margin-top: 0.5rem;
        }
        .contact-header p {
            color: #9CA3AF;
            font-size: 1rem;
            margin-top: 0.75rem;
            line-height: 1.6;
        }

        .contact-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
        }
        @media (min-width: 1024px) {
            .contact-grid { grid-template-columns: 1.1fr 1fr; gap: 3rem; }
        }

        .contact-channels-col {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }
        .channel-card {
            background-color: var(--color-surface-feature);
            border: 1px solid var(--color-border-card);
            border-radius: 1.25rem;
            padding: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 1.25rem;
            transition: all 0.25s ease;
        }
        .channel-card:hover {
            border-color: rgba(222, 219, 200, 0.3);
            transform: translateY(-2px);
            background-color: #1e1e1e;
        }
        .channel-icon-box {
            width: 44px;
            height: 44px;
            border-radius: 0.85rem;
            background: rgba(222, 219, 200, 0.08);
            border: 1px solid rgba(222, 219, 200, 0.15);
            color: var(--color-cream-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .channel-details {
            flex-grow: 1;
        }
        .channel-label {
            font-size: 0.6875rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #9CA3AF;
            font-weight: 700;
        }
        .channel-primary-val {
            font-size: 1.125rem;
            font-weight: 700;
            color: #E1E0CC;
            margin-top: 0.25rem;
        }
        .channel-primary-val a {
            color: #E1E0CC;
            text-decoration: none;
            transition: color 0.2s ease;
        }
        .channel-primary-val a:hover {
            color: var(--color-cream-primary);
            text-decoration: underline;
        }
        .channel-sub-val {
            font-size: 0.8125rem;
            color: #9CA3AF;
            margin-top: 0.25rem;
        }
        .channel-note {
            font-size: 0.75rem;
            color: #9CA3AF;
            margin-top: 0.4rem;
        }
        .social-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.35rem 0.75rem;
            border-radius: 9999px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #E1E0CC;
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .social-badge:hover {
            background: rgba(222, 219, 200, 0.2);
            color: #FFFFFF;
            border-color: var(--color-cream-primary);
        }

        .inquiry-form-card {
            background-color: var(--color-surface-feature);
            border: 1px solid var(--color-border-card);
            border-radius: 1.5rem;
            padding: 2rem;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.6);
        }
        .inquiry-form-header {
            margin-bottom: 1.75rem;
        }
        .inquiry-form-header h3 {
            font-size: 1.375rem;
            font-weight: 600;
            color: #E1E0CC;
        }
        .inquiry-form-header p {
            font-size: 0.8125rem;
            color: #9CA3AF;
            margin-top: 0.35rem;
            line-height: 1.5;
        }

        .inquiry-form-body {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.25rem;
        }
        @media (min-width: 640px) {
            .form-row { grid-template-columns: repeat(2, 1fr); }
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }
        .form-group label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #D1D5DB;
            letter-spacing: 0.02em;
        }
        .form-group label .req {
            color: #EF4444;
        }
        .inq-input {
            width: 100%;
            background-color: rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 0.75rem;
            padding: 0.65rem 0.85rem;
            color: #E1E0CC;
            font-family: var(--font-sans);
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }
        .inq-input:focus {
            outline: none;
            border-color: var(--color-cream-primary);
            box-shadow: 0 0 0 2px rgba(222, 219, 200, 0.2);
            background-color: rgba(0, 0, 0, 0.6);
        }
        .inq-select option {
            background-color: #181818;
            color: #E1E0CC;
        }
        .inq-textarea {
            resize: vertical;
            min-height: 80px;
        }

        .btn-submit-inquiry {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            width: 100%;
            padding: 0.85rem 1.5rem;
            background: var(--color-cream-primary);
            border: none;
            border-radius: 9999px;
            color: #000000;
            font-family: var(--font-sans);
            font-size: 0.9375rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            margin-top: 0.5rem;
        }
        .btn-submit-inquiry:hover {
            transform: translateY(-2px);
            background: #FFFFFF;
            box-shadow: 0 8px 24px rgba(222, 219, 200, 0.3);
        }
        .inquiry-footnote {
            font-size: 0.6875rem;
            color: #6B7280;
            text-align: center;
            margin-top: 0.5rem;
            line-height: 1.4;
        }
        .contact-alert {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.85rem 1.25rem;
            border-radius: 0.75rem;
            font-size: 0.875rem;
        }
        .contact-alert.success {
            background: rgba(16, 185, 129, 0.12);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #86EFAC;
        }
        .contact-alert.error {
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #FCA5A5;
        }

        /* ----------------------------------------------------------------- */
        /* ENHANCED MULTI-COLUMN FOOTER                                      */
        /* ----------------------------------------------------------------- */
        .footer-top-grid {
            max-width: 80rem;
            margin: 0 auto 3rem auto;
            display: grid;
            grid-template-columns: 1fr;
            gap: 2.5rem;
        }
        @media (min-width: 640px) {
            .footer-top-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (min-width: 1024px) {
            .footer-top-grid { grid-template-columns: 1.5fr 1fr 1fr 1.25fr; }
        }
        .footer-col-brand .footer-logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
        }
        .footer-tagline {
            font-size: 0.8125rem;
            color: #9CA3AF;
            line-height: 1.6;
            margin-bottom: 1.25rem;
        }
        .footer-contact-mini {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
            font-size: 0.75rem;
            color: #9CA3AF;
        }
        .footer-contact-mini a {
            color: var(--color-cream-primary);
            text-decoration: none;
        }
        .footer-contact-mini a:hover {
            text-decoration: underline;
        }
        .footer-col-nav h4 {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #E1E0CC;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        .footer-col-nav ul {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
        }
        .footer-col-nav li a {
            font-size: 0.8125rem;
            color: #9CA3AF;
            text-decoration: none;
            transition: color 0.2s ease;
        }
        .footer-col-nav li a:hover {
            color: var(--color-cream-primary);
        }
        .footer-hours-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 0.75rem;
            padding: 1rem;
            font-size: 0.75rem;
            color: #9CA3AF;
            line-height: 1.5;
        }
    </style>
</head>
<body>

    <!-- ================================================================= -->
    <!-- SECTION 1: HERO (Fullscreen Inset Container)                      -->
    <!-- ================================================================= -->
    <section id="hero" class="hero-section">
        <div class="hero-container">
            
            <!-- Ambient Video Background Layer -->
            <video 
                class="hero-video-bg" 
                autoplay 
                loop 
                muted 
                playsinline
                poster="/assets/images/room-placeholder.jpg"
            >
                <source src="https://d8j0ntlcm91z4.cloudfront.net/user_38xzZboKViGWJOttwIXH07lWA1P/hf_20260405_170732_8a9ccda6-5cff-4628-b164-059c500a2b41.mp4" type="video/mp4" />
            </video>
            <div class="hero-gradient-overlay"></div>
            <div class="hero-noise-overlay noise-overlay"></div>

            <!-- Hanging Pill Navbar -->
            <div class="hanging-nav-wrap">
                <nav class="hanging-nav">
                    <a href="#hero" class="nav-brand">
                        <span class="nav-brand-dot"></span>
                        <span>RJM</span>
                    </a>

                    <div class="nav-links-desktop">
                        <a href="#about" class="nav-link">The Residence</a>
                        <a href="#features" class="nav-link">Amenities</a>
                        <a href="#rooms" class="nav-link">Rooms & Rates</a>
                        <a href="#location" class="nav-link">
                            <span>Location & GPS</span>
                            <span style="width: 6px; height: 6px; border-radius: 50%; background-color: #10B981;"></span>
                        </a>
                        <a href="#contact" class="nav-link" style="color: var(--color-cream-primary);">
                            <span>Contact & Inquire</span>
                        </a>
                    </div>

                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <?php if ($sessionRole && $dashboardUrl): ?>
                            <a href="<?= htmlspecialchars($dashboardUrl) ?>" class="btn-nav-login" style="background-color: var(--color-cream-primary); color: #000000;">
                                <span>Dashboard</span>
                                <svg class="icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                            </a>
                        <?php else: ?>
                            <a href="/login" class="btn-nav-login">
                                <span>Resident Login</span>
                                <svg class="icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/></svg>
                            </a>
                        <?php endif; ?>

                        <!-- Mobile Hamburger Button -->
                        <button type="button" class="btn-mobile-nav" id="mobile-menu-toggle" aria-label="Toggle navigation menu">
                            <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                        </button>
                    </div>
                </nav>

                <!-- Mobile Navigation Drawer -->
                <div class="mobile-drawer" id="mobile-drawer">
                    <div class="mobile-drawer-inner">
                        <a href="#about" class="mobile-nav-link">
                            <span>The Residence</span>
                            <span style="font-size: 0.75rem; color: #6B7280;">01</span>
                        </a>
                        <a href="#features" class="mobile-nav-link">
                            <span>Amenities & Operations</span>
                            <span style="font-size: 0.75rem; color: #6B7280;">02</span>
                        </a>
                        <a href="#rooms" class="mobile-nav-link">
                            <span>Rooms & Rates</span>
                            <span style="font-size: 0.75rem; color: #10B981; font-weight: 700;">₱1,600+</span>
                        </a>
                        <a href="#location" class="mobile-nav-link">
                            <span>Location & GPS</span>
                            <span style="font-size: 0.75rem; color: #6B7280;">Zone 3</span>
                        </a>
                        <a href="#contact" class="mobile-nav-link" style="color: var(--color-cream-primary); font-weight: 700;">
                            <span>Contact Us & Inquiries</span>
                            <span>📞</span>
                        </a>
                        <div style="margin-top: 0.5rem; padding-top: 0.85rem; border-top: 1px solid rgba(255,255,255,0.08); display: flex; flex-direction: column; gap: 0.6rem;">
                            <a href="<?= $dashboardUrl ? htmlspecialchars($dashboardUrl) : '/login' ?>" class="btn-pill-primary" style="width: 100%; justify-content: center;">
                                <span><?= $dashboardUrl ? 'Access Dashboard' : 'Resident & Staff Login' ?></span>
                            </a>
                            <a href="<?= $contactInfo['primary_tel'] ?>" class="btn-pill-secondary" style="width: 100%; justify-content: center; font-size: 0.8125rem;">
                                <span>Call: <?= htmlspecialchars($contactInfo['primary_phone']) ?></span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Content (12-Column Responsive Layout) -->
            <div class="hero-bottom-grid">
                
                <!-- Left: Display Typography with WordsPullUp & Asterisk -->
                <div>
                    <div class="hero-badge">
                        <span class="hero-badge-dot"></span>
                        <span>Zone 3, Tupi, South Cotabato</span>
                    </div>

                    <h1 class="hero-giant-title hero-pull-heading">
                        <span class="word-wrap"><span class="word-inner">RJM</span></span>
                        <span class="word-wrap">
                            <span class="word-inner">RESIDENCE</span>
                            <span class="hero-asterisk">*</span>
                        </span>
                    </h1>
                </div>

                <!-- Right: Atmospheric Narrative & Primary CTA -->
                <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                    <p class="hero-subtext hero-fade-sub">
                        A modern student and professional sanctuary crafted for focus, security, and effortless living in Tupi, South Cotabato. Fusing serene private rooms, online rent ledgers, AI-triaged repairs, and round-the-clock security.
                    </p>

                    <div class="hero-actions hero-fade-cta">
                        <a href="<?= $dashboardUrl ? htmlspecialchars($dashboardUrl) : '/login' ?>" class="btn-pill-primary">
                            <span><?= $dashboardUrl ? 'Resume Portal Dashboard' : 'Enter Resident Portal' ?></span>
                            <span class="circle-arrow">
                                <svg class="icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                            </span>
                        </a>

                        <a href="#rooms" class="btn-pill-secondary">
                            <svg class="icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                            <span>Rooms & Rates</span>
                        </a>

                        <a href="#contact" class="btn-pill-secondary" style="border-color: rgba(222, 219, 200, 0.4); color: var(--color-cream-primary);">
                            <svg class="icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                            <span>Inquire & Contact</span>
                        </a>
                    </div>

                    <!-- Hero Contact Quick Ticker -->
                    <div class="hero-contact-ticker">
                        <div class="ticker-item">
                            <span class="ticker-dot" style="width:6px;height:6px;border-radius:50%;background:#10B981;"></span>
                            <span class="ticker-label">Hotline:</span>
                            <a href="<?= $contactInfo['primary_tel'] ?>" class="ticker-link"><?= htmlspecialchars($contactInfo['primary_phone']) ?></a>
                        </div>
                        <span class="ticker-sep">•</span>
                        <div class="ticker-item">
                            <span class="ticker-label">Email:</span>
                            <a href="mailto:<?= htmlspecialchars($contactInfo['inquiry_email']) ?>" class="ticker-link"><?= htmlspecialchars($contactInfo['inquiry_email']) ?></a>
                        </div>
                        <span class="ticker-sep">•</span>
                        <div class="ticker-item">
                            <span class="ticker-label">Visiting Hours:</span>
                            <span>Mon–Sat 8AM–6PM</span>
                        </div>
                        <span class="ticker-sep">•</span>
                        <div class="ticker-item">
                            <span class="ticker-label">Gate Curfew:</span>
                            <span style="color: #10B981; font-weight: 600;">10:00 PM</span>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </section>

    <!-- ================================================================= -->
    <!-- SECTION 2: ABOUT / THE RESIDENCE PHILOSOPHY                       -->
    <!-- ================================================================= -->
    <section id="about" class="about-section">
        <div class="about-card">
            
            <span class="section-label">Living Elevated</span>

            <h2 class="about-heading">
                <span class="word-wrap"><span class="word-inner">Designed</span></span>
                <span class="word-wrap"><span class="word-inner">for</span></span>
                <span class="word-wrap"><span class="word-inner">scholars</span></span>
                <span class="word-wrap"><span class="word-inner">&</span></span>
                <span class="word-wrap"><span class="word-inner">creators,</span></span>
                <span class="word-wrap"><span class="word-inner font-serif italic" style="color: var(--color-cream-primary);">a distinguished</span></span>
                <span class="word-wrap"><span class="word-inner font-serif italic" style="color: var(--color-cream-primary);">urban</span></span>
                <span class="word-wrap"><span class="word-inner font-serif italic" style="color: var(--color-cream-primary);">refuge.</span></span>
            </h2>

            <!-- Scroll-Linked Character Opacity Reveal -->
            <p id="scroll-char-container" class="about-narrative">
                <?php
                $aboutNarrative = "Over the years, RJM Boardinghouse has stood as a quiet sanctuary in Tupi, South Cotabato. We reject the crowded chaos of typical dormitories. Here, high ceilings, natural ventilation, and quiet study hours converge with an intelligent digital infrastructure—enabling effortless online ledger tracking, prompt AI maintenance resolution, and a protected 24/7 environment where your aspirations can flourish.";
                $chars = mb_str_split($aboutNarrative);
                foreach ($chars as $idx => $c):
                    if ($c === ' '):
                        echo ' ';
                    else:
                        echo '<span class="char-reveal" data-char-index="' . $idx . '">' . htmlspecialchars($c) . '</span>';
                    endif;
                endforeach;
                ?>
            </p>

            <!-- Metrics -->
            <div class="metrics-row">
                <div>
                    <div class="metric-val"><?= (int) ($stats['vacant_beds'] ?? 0) ?> Beds</div>
                    <div class="metric-label">Immediate Room Vacancies</div>
                </div>
                <div>
                    <div class="metric-val">24 / 7</div>
                    <div class="metric-label">On-Site Caretakers & SOS</div>
                </div>
                <div>
                    <div class="metric-val">100%</div>
                    <div class="metric-label">Water & Power Backup</div>
                </div>
                <div>
                    <div class="metric-val">Zone 3</div>
                    <div class="metric-label">Central Tupi Location</div>
                </div>
            </div>

        </div>
    </section>

    <!-- ================================================================= -->
    <!-- SECTION 3: FEATURES (4-Column Bento Cards Grid)                   -->
    <!-- ================================================================= -->
    <section id="features" class="features-section">
        <div class="features-container">
            
            <div class="features-header">
                <span class="section-label" style="margin-bottom: 0.75rem;">Resident Architecture</span>
                <h2>Studio-grade workflows for modern residents.</h2>
                <p>Built for pure comfort. Powered by transparent digital operations.</p>
            </div>

            <div class="bento-grid">
                
                <!-- Card 1: Ambient Video Showcase -->
                <div class="bento-card bento-video-card">
                    <video 
                        class="bento-video-bg" 
                        autoplay 
                        loop 
                        muted 
                        playsinline
                    >
                        <source src="https://d8j0ntlcm91z4.cloudfront.net/user_38xzZboKViGWJOttwIXH07lWA1P/hf_20260406_133058_0504132a-0cf3-4450-a370-8ea3b05c95d4.mp4" type="video/mp4" />
                    </video>
                    <div class="bento-video-overlay"></div>
                    <div class="noise-overlay" style="position: absolute; inset: 0; opacity: 0.35; mix-blend-mode: overlay; pointer-events: none; z-index: 3;"></div>

                    <div style="position: relative; z-index: 5;">
                        <span style="display: inline-block; padding: 0.25rem 0.6rem; border-radius: 0.35rem; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); font-size: 0.625rem; font-weight: 700; color: var(--color-cream-primary); text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 0.5rem; border: 1px solid rgba(255,255,255,0.1);">
                            Living Ambiance
                        </span>
                        <h3 style="font-size: 1.5rem; font-weight: 500; color: #E1E0CC; font-family: var(--font-serif); font-style: italic;">Your private retreat.</h3>
                        <p style="font-size: 0.75rem; color: #D1D5DB; margin-top: 0.35rem; line-height: 1.5;">Clean, ventilated private rooms, dedicated study desks, and secure storage lockers.</p>
                    </div>
                </div>

                <!-- Card 2: 01 Smart Rent & Balance Ledger -->
                <div class="bento-card">
                    <div>
                        <div class="card-top-row">
                            <div class="card-icon-box">
                                <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                            </div>
                            <span class="card-num">01</span>
                        </div>

                        <h3 class="card-title">Smart Ledger & Rent</h3>

                        <ul class="checklist">
                            <li class="checklist-item">
                                <svg class="icon-xs checklist-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                <span>Automated monthly rent balance calculations</span>
                            </li>
                            <li class="checklist-item">
                                <svg class="icon-xs checklist-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                <span>Instant digital payment receipts with audit ID</span>
                            </li>
                            <li class="checklist-item">
                                <svg class="icon-xs checklist-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                <span>Transparent ₱5/day overdue penalty rule</span>
                            </li>
                            <li class="checklist-item">
                                <svg class="icon-xs checklist-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                <span>Exportable PDF proof of residency records</span>
                            </li>
                        </ul>
                    </div>

                    <a href="/login" class="card-footer-link">
                        <span>Access ledger portal</span>
                        <svg class="icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </a>
                </div>

                <!-- Card 3: 02 AI-Triage Maintenance Dispatch -->
                <div class="bento-card">
                    <div>
                        <div class="card-top-row">
                            <div class="card-icon-box">
                                <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                            </div>
                            <span class="card-num">02</span>
                        </div>

                        <h3 class="card-title">AI-Triage Repairs</h3>

                        <ul class="checklist">
                            <li class="checklist-item">
                                <svg class="icon-xs checklist-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                <span>FastAPI Python automated severity scoring</span>
                            </li>
                            <li class="checklist-item">
                                <svg class="icon-xs checklist-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                <span>Photo diagnostic attachments & notes</span>
                            </li>
                            <li class="checklist-item">
                                <svg class="icon-xs checklist-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                <span>Automatic technician task dispatch</span>
                            </li>
                            <li class="checklist-item">
                                <svg class="icon-xs checklist-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                <span>Graceful offline resilience fallback</span>
                            </li>
                        </ul>
                    </div>

                    <a href="/login" class="card-footer-link">
                        <span>Submit repair ticket</span>
                        <svg class="icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </a>
                </div>

                <!-- Card 4: 03 SOS Security & Incident Hub -->
                <div class="bento-card">
                    <div>
                        <div class="card-top-row">
                            <div class="card-icon-box">
                                <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            </div>
                            <span class="card-num">03</span>
                        </div>

                        <h3 class="card-title">SOS Security Shield</h3>

                        <ul class="checklist">
                            <li class="checklist-item">
                                <svg class="icon-xs checklist-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                <span>One-tap instant silent SOS alert trigger</span>
                            </li>
                            <li class="checklist-item">
                                <svg class="icon-xs checklist-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                <span>Real-time on-duty staff alarm dispatch</span>
                            </li>
                            <li class="checklist-item">
                                <svg class="icon-xs checklist-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                <span>Curfew & visitor digital access monitoring</span>
                            </li>
                            <li class="checklist-item">
                                <svg class="icon-xs checklist-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                <span>Secure QR pass token authentication</span>
                            </li>
                        </ul>
                    </div>

                    <a href="/login" class="card-footer-link">
                        <span>Review security protocols</span>
                        <svg class="icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </a>
                </div>

            </div>

        </div>
    </section>

    <!-- ================================================================= -->
    <!-- SECTION 4: ROOMS & RATES (Curated Resident Living Tiers)           -->
    <!-- ================================================================= -->
    <section id="rooms" class="rooms-section">
        <div class="rooms-container">
            <div class="rooms-header">
                <div class="section-label" style="display: inline-flex; align-items: center; gap: 0.4rem; color: var(--color-cream-primary);">
                    <span style="width: 6px; height: 6px; border-radius: 50%; background-color: var(--color-cream-primary);"></span>
                    <span>Curated Living Accommodations</span>
                </div>
                <h2>Thoughtfully designed private & shared sanctuaries.</h2>
                <p>Transparent monthly leasing with study-optimized lighting, high-speed Wi-Fi, and monitored security. Select your preferred tier to submit an inquiry.</p>
            </div>

            <!-- 3-Column Room Tiers -->
            <div class="rooms-grid">
                <?php foreach ($roomTiers as $tier): ?>
                    <div class="room-tier-card <?= !empty($tier['popular']) ? 'tier-popular' : '' ?>">
                        <?php if (!empty($tier['popular'])): ?>
                            <div class="tier-popular-pill">Most Requested</div>
                        <?php endif; ?>

                        <div class="tier-top">
                            <span class="tier-badge"><?= htmlspecialchars($tier['badge']) ?></span>
                            <h3 class="tier-title"><?= htmlspecialchars($tier['type']) ?></h3>
                            <div class="tier-capacity">
                                <svg class="icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                <span><?= htmlspecialchars($tier['capacity']) ?></span>
                            </div>
                        </div>

                        <div class="tier-price-row">
                            <span class="tier-price"><?= htmlspecialchars($tier['price']) ?></span>
                            <span class="tier-period"><?= htmlspecialchars($tier['period']) ?></span>
                        </div>

                        <ul class="tier-features">
                            <?php foreach ($tier['features'] as $feat): ?>
                                <li class="tier-feature-item">
                                    <svg class="icon-xs" style="color: #10B981;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                    <span><?= htmlspecialchars($feat) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <div class="tier-action">
                            <a href="#contact" class="btn-tier-inquire" data-room-type="<?= htmlspecialchars($tier['type']) ?>">
                                <span><?= htmlspecialchars($tier['cta_label']) ?></span>
                                <svg class="icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Inclusions Highlight Banner -->
            <div class="inclusions-banner">
                <div class="inclusions-title">All Accommodations Include Standard Sanctuary Privileges:</div>
                <div class="inclusions-items">
                    <span class="inc-pill">📶 High-Speed Fiber Wi-Fi</span>
                    <span class="inc-pill">💧 Pure Filtered Water</span>
                    <span class="inc-pill">⚡ Individual Power Submetering</span>
                    <span class="inc-pill">🔒 Heavy-Duty Lockers</span>
                    <span class="inc-pill">🛡️ 24/7 CCTV & QR Pass Gate</span>
                    <span class="inc-pill">📚 Quiet Study Hours (10PM-6AM)</span>
                </div>
            </div>
        </div>
    </section>

    <!-- ================================================================= -->
    <!-- SECTION 5: GPS LOCATION & GOOGLE MAPS SHOWCASE                    -->
    <!-- ================================================================= -->
    <section id="location" class="location-section">
        <div class="location-container">
            
            <div class="location-header">
                <div class="section-label" style="display: inline-flex; align-items: center; gap: 0.4rem; color: #10B981; border-color: rgba(16, 185, 129, 0.3); background-color: rgba(16, 185, 129, 0.08);">
                    <span style="width: 6px; height: 6px; border-radius: 50%; background-color: #10B981;" class="animate-radar"></span>
                    <span>Verified GPS Coordinates</span>
                </div>
                <h2>Sanctuary Compass & Precise Location</h2>
                <p>Located in Zone 3, Tupi, South Cotabato—shielded from loud thoroughfares yet minutes from major universities and town centers.</p>
            </div>

            <div class="location-grid">
                
                <!-- Left: Dark Framed Interactive Map -->
                <div class="map-frame-card">
                    <div class="map-wrapper">
                        <iframe 
                            class="map-iframe"
                            src="https://maps.google.com/maps?q=<?= $mapData['latitude'] ?>,<?= $mapData['longitude'] ?>&hl=en&z=17&output=embed"
                            allowfullscreen="" 
                            loading="lazy" 
                            referrerpolicy="no-referrer-when-downgrade"
                            title="RJM Boardinghouse GPS Location"
                        ></iframe>

                        <!-- Floating Live GPS Pill -->
                        <div class="floating-gps-pill">
                            <span style="width: 8px; height: 8px; border-radius: 50%; background-color: #10B981;"></span>
                            <span style="font-family: monospace;"><?= number_format($mapData['latitude'], 6) ?>° N, <?= number_format($mapData['longitude'], 6) ?>° E</span>
                        </div>

                        <!-- Direct App Launch Button -->
                        <a 
                            href="<?= htmlspecialchars($mapData['short_url']) ?>" 
                            target="_blank" 
                            rel="noopener noreferrer"
                            class="floating-map-btn"
                        >
                            <svg class="icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            <span>Open in Google Maps</span>
                        </a>
                    </div>

                    <div class="map-address-bar">
                        <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                            <svg class="icon-xs" style="color: var(--color-cream-primary);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                            <span style="color: #E1E0CC; font-weight: 600;"><?= htmlspecialchars($mapData['display_name']) ?></span>
                            <span>•</span>
                            <span><?= htmlspecialchars($mapData['address']) ?></span>
                        </div>
                        <button 
                            type="button"
                            onclick="navigator.clipboard.writeText('<?= $mapData['latitude'] ?>, <?= $mapData['longitude'] ?>'); this.innerText = 'Copied!'; setTimeout(() => this.innerText = 'Copy GPS', 2000);"
                            style="background: none; border: none; font-family: monospace; font-size: 0.75rem; color: var(--color-cream-primary); cursor: pointer; text-decoration: underline;"
                        >
                            Copy GPS
                        </button>
                    </div>
                </div>

                <!-- Right: Landmarks & Commute Guide -->
                <div class="compass-col">
                    
                    <div class="landmarks-card">
                        <div class="landmarks-title-row">
                            <h3>Nearby Landmarks</h3>
                            <span style="font-size: 0.75rem; color: var(--color-cream-primary); font-family: monospace;">Commute</span>
                        </div>

                        <?php foreach ($mapData['landmarks'] as $landmark): ?>
                            <div class="landmark-item">
                                <div class="landmark-info">
                                    <div class="landmark-icon">
                                        <?php if ($landmark['type'] === 'University' || $landmark['type'] === 'College'): ?>
                                            <svg class="icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                                        <?php elseif ($landmark['type'] === 'Healthcare'): ?>
                                            <svg class="icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
                                        <?php else: ?>
                                            <svg class="icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m4.93 4.93 4.24 4.24"/><path d="m14.83 9.17 4.24-4.24"/><path d="m14.83 14.83 4.24 4.24"/><path d="m9.17 14.83-4.24 4.24"/></svg>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="landmark-name"><?= htmlspecialchars($landmark['name']) ?></div>
                                        <div class="landmark-type"><?= htmlspecialchars($landmark['type']) ?></div>
                                    </div>
                                </div>
                                <span class="landmark-distance"><?= htmlspecialchars($landmark['distance']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="buffer-card">
                        <div class="buffer-title">
                            <svg class="icon-sm" style="color: #10B981;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            <span>Quiet Residential Sanctuary</span>
                        </div>
                        <p class="buffer-desc">
                            Positioned away from high-noise traffic corridors, offering low ambient decibels optimal for night study, thesis writing, and uninterrupted rest. Accessible by all standard tricycle routes.
                        </p>
                    </div>

                </div>

            </div>

        </div>
    </section>

    <!-- ================================================================= -->
    <!-- SECTION 6: CONTACT & DIRECT INQUIRIES                             -->
    <!-- ================================================================= -->
    <section id="contact" class="contact-section">
        <div class="contact-container">
            
            <div class="contact-header">
                <div class="section-label" style="display: inline-flex; align-items: center; gap: 0.4rem; color: #10B981; border-color: rgba(16, 185, 129, 0.3); background-color: rgba(16, 185, 129, 0.08);">
                    <span style="width: 6px; height: 6px; border-radius: 50%; background-color: #10B981;"></span>
                    <span>Direct Communication & Inquiries</span>
                </div>
                <h2>Connect directly with house management.</h2>
                <p>Whether you wish to schedule a personal room viewing, check immediate vacancies, or ask about leasing terms, our team is accessible via call, SMS, email, or direct message.</p>
            </div>

            <!-- Flash alerts if submitted without AJAX -->
            <?php if (!empty($inquirySuccess)): ?>
                <div class="contact-alert success" style="margin-bottom: 2rem;">
                    <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    <span><?= htmlspecialchars($inquirySuccess) ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($inquiryError)): ?>
                <div class="contact-alert error" style="margin-bottom: 2rem;">
                    <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span><?= htmlspecialchars($inquiryError) ?></span>
                </div>
            <?php endif; ?>

            <div class="contact-grid">
                
                <!-- Left Column: Direct Communication Channels -->
                <div class="contact-channels-col">
                    
                    <!-- Channel 1: Phone Hotlines & Caretakers -->
                    <div class="channel-card">
                        <div class="channel-icon-box">
                            <svg class="icon-md" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        </div>
                        <div class="channel-details">
                            <span class="channel-label">Telephone & Mobile Hotlines</span>
                            <div class="channel-primary-val">
                                <a href="<?= $contactInfo['primary_tel'] ?>"><?= htmlspecialchars($contactInfo['primary_phone']) ?></a>
                            </div>
                            <div class="channel-sub-val">
                                Landline: <a href="<?= $contactInfo['landline_tel'] ?>" style="color: #E1E0CC; text-decoration: none;"><?= htmlspecialchars($contactInfo['landline']) ?></a>
                            </div>
                            <div class="channel-note">
                                <span>👥 Contact: <?= htmlspecialchars($contactInfo['caretakers']) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Channel 2: Official Email Addresses -->
                    <div class="channel-card">
                        <div class="channel-icon-box">
                            <svg class="icon-md" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                        </div>
                        <div class="channel-details">
                            <span class="channel-label">Official Email Contacts</span>
                            <div class="channel-primary-val">
                                <a href="mailto:<?= htmlspecialchars($contactInfo['inquiry_email']) ?>"><?= htmlspecialchars($contactInfo['inquiry_email']) ?></a>
                            </div>
                            <div class="channel-sub-val">
                                Administration: <a href="mailto:<?= htmlspecialchars($contactInfo['admin_email']) ?>" style="color: #9CA3AF; text-decoration: none;"><?= htmlspecialchars($contactInfo['admin_email']) ?></a>
                            </div>
                            <div class="channel-note">
                                <span>⏱️ Average inquiry response: within 2 hours</span>
                            </div>
                        </div>
                    </div>

                    <!-- Channel 3: Social & Instant Messaging -->
                    <div class="channel-card">
                        <div class="channel-icon-box">
                            <svg class="icon-md" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></svg>
                        </div>
                        <div class="channel-details">
                            <span class="channel-label">Instant Messaging & Socials</span>
                            <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 0.5rem;">
                                <a href="<?= htmlspecialchars($contactInfo['facebook_url']) ?>" target="_blank" rel="noopener noreferrer" class="social-badge">
                                    <svg class="icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
                                    <span>Facebook Page</span>
                                </a>
                                <a href="<?= htmlspecialchars($contactInfo['messenger_url']) ?>" target="_blank" rel="noopener noreferrer" class="social-badge">
                                    <svg class="icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                                    <span>Messenger</span>
                                </a>
                                <a href="<?= $contactInfo['primary_tel'] ?>" class="social-badge">
                                    <span>Viber / WhatsApp</span>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Channel 4: Visiting Schedule & Curfew Policy -->
                    <div class="channel-card">
                        <div class="channel-icon-box">
                            <svg class="icon-md" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        </div>
                        <div class="channel-details">
                            <span class="channel-label">Office & Viewing Hours</span>
                            <div style="font-size: 0.9375rem; color: #E1E0CC; margin-top: 0.25rem;">
                                <?= htmlspecialchars($contactInfo['office_hours']) ?>
                            </div>
                            <div style="font-size: 0.8125rem; color: #10B981; margin-top: 0.35rem; display: flex; align-items: center; gap: 0.4rem;">
                                <span style="width: 6px; height: 6px; border-radius: 50%; background: #10B981;"></span>
                                <span>Resident Gate Curfew: <?= htmlspecialchars($contactInfo['curfew_hours']) ?></span>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Right Column: Interactive Room Availability Inquiry Form -->
                <div class="inquiry-form-card">
                    <div class="inquiry-form-header">
                        <h3>Inquire for Room Availability</h3>
                        <p>Fill out the details below. Our management team will check availability and reach out to you via SMS or phone call.</p>
                    </div>

                    <form id="inquiry-form" action="/inquire" method="POST" class="inquiry-form-body">
                        <!-- Spam trap: hidden from people and screen readers; bots that fill it are ignored. -->
                        <div aria-hidden="true" style="position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden">
                            <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="inq-name">Your Full Name <span class="req">*</span></label>
                                <input type="text" id="inq-name" name="name" required placeholder="e.g. Maria Santos" class="inq-input" />
                            </div>
                            <div class="form-group">
                                <label for="inq-phone">Contact Phone / Mobile <span class="req">*</span></label>
                                <input type="tel" id="inq-phone" name="phone" required placeholder="e.g. 0917 123 4567" class="inq-input" />
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="inq-email">Email Address (Optional)</label>
                                <input type="email" id="inq-email" name="email" placeholder="e.g. maria@gmail.com" class="inq-input" />
                            </div>
                            <div class="form-group">
                                <label for="inq-room">Preferred Room Tier</label>
                                <select id="inq-room" name="room_type" class="inq-input inq-select">
                                    <option value="Solo Executive Room">Solo Executive Room (₱3,500/mo)</option>
                                    <option value="Twin Sharing Scholar Suite" selected>Twin Sharing Scholar Suite (₱2,200/mo)</option>
                                    <option value="Quad Bedspace Sanctuary">Quad Bedspace Sanctuary (₱1,600/mo)</option>
                                    <option value="General Inquiry / Visit Booking">General Inquiry / Physical Viewing</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="inq-date">Target Move-in / Viewing Date</label>
                            <input type="date" id="inq-date" name="move_in_date" class="inq-input" />
                        </div>

                        <div class="form-group">
                            <label for="inq-message">Questions or Specific Requirements</label>
                            <textarea id="inq-message" name="message" rows="3" placeholder="Tell us if you are a student (SEAIT, NDMU, etc.), worker, or have questions about amenities or study hours..." class="inq-input inq-textarea"></textarea>
                        </div>

                        <!-- Instant feedback banner -->
                        <div id="inquiry-feedback" style="display: none; padding: 1rem; border-radius: 0.75rem; font-size: 0.875rem; margin-top: 0.5rem;"></div>

                        <button type="submit" id="btn-submit-inquiry" class="btn-submit-inquiry">
                            <span>Submit Room Inquiry</span>
                            <svg class="icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                        </button>

                        <p class="inquiry-footnote">
                            🔒 No commitment or reservation fee required. All data is protected under our privacy standards.
                        </p>
                    </form>
                </div>

            </div>

        </div>
    </section>

    <!-- ================================================================= -->
    <!-- SECTION 7: GATEWAY TO LOGIN & PORTAL                              -->
    <!-- ================================================================= -->
    <section class="gateway-section">
        <div class="gateway-container">
            
            <span class="section-label">Seamless Digital Gateway</span>
            <h2 class="gateway-heading">Ready to step into your sanctuary?</h2>
            <p class="gateway-desc">
                Log in to review your balance, inspect maintenance tickets, trigger safety requests, or claim your room token.
            </p>

            <div class="gateway-actions">
                <a href="<?= $dashboardUrl ? htmlspecialchars($dashboardUrl) : '/login' ?>" class="btn-pill-primary" style="padding: 0.75rem 1rem 0.75rem 2rem; font-size: 1rem;">
                    <span><?= $dashboardUrl ? 'Resume Resident Dashboard' : 'Proceed to Resident & Staff Login' ?></span>
                    <span class="circle-arrow">
                        <svg class="icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </span>
                </a>

                <a href="#contact" class="btn-pill-secondary" style="padding: 0.85rem 1.6rem; border-color: rgba(222, 219, 200, 0.4); color: var(--color-cream-primary);">
                    <svg class="icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    <span>Inquire for Vacancies</span>
                </a>

                <a href="<?= htmlspecialchars($mapData['short_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn-pill-secondary" style="padding: 0.85rem 1.6rem;">
                    <span>Get GPS Directions</span>
                    <svg class="icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14L21 3"/></svg>
                </a>
            </div>

            <div class="roles-pills">
                <span class="role-pill"><span class="role-dot" style="background-color: #10B981;"></span> Boarder Portal</span>
                <span>•</span>
                <span class="role-pill"><span class="role-dot" style="background-color: #3B82F6;"></span> Staff Maintenance</span>
                <span>•</span>
                <span class="role-pill"><span class="role-dot" style="background-color: #F59E0B;"></span> Admin Command Center</span>
            </div>

        </div>
    </section>

    <!-- ================================================================= -->
    <!-- ENHANCED FOOTER                                                   -->
    <!-- ================================================================= -->
    <footer class="footer-bar">
        <div class="footer-top-grid">
            <div class="footer-col-brand">
                <div class="footer-logo">
                    <span class="nav-brand-dot"></span>
                    <span style="color: #E1E0CC; font-weight: 800; letter-spacing: 0.08em; font-size: 0.9375rem;">RJM BOARDINGHOUSE</span>
                </div>
                <p class="footer-tagline">
                    A modern student and professional sanctuary crafted for focus, security, and effortless living in Zone 3, Tupi, South Cotabato.
                </p>
                <div class="footer-contact-mini">
                    <div>📞 Hotline: <a href="<?= $contactInfo['primary_tel'] ?>"><?= htmlspecialchars($contactInfo['primary_phone']) ?></a></div>
                    <div>✉️ Email: <a href="mailto:<?= htmlspecialchars($contactInfo['inquiry_email']) ?>"><?= htmlspecialchars($contactInfo['inquiry_email']) ?></a></div>
                    <div>📍 Location: <?= htmlspecialchars($contactInfo['address_line1']) ?>, <?= htmlspecialchars($contactInfo['address_line2']) ?></div>
                </div>
            </div>

            <div class="footer-col-nav">
                <h4>Navigation</h4>
                <ul>
                    <li><a href="#hero">Sanctuary Home</a></li>
                    <li><a href="#about">The Residence</a></li>
                    <li><a href="#features">Amenities & Operations</a></li>
                    <li><a href="#rooms">Rooms & Rates</a></li>
                    <li><a href="#location">Location & Compass</a></li>
                    <li><a href="#contact">Contact & Inquire</a></li>
                </ul>
            </div>

            <div class="footer-col-nav">
                <h4>Resident & Staff</h4>
                <ul>
                    <li><a href="/login">Portal Login</a></li>
                    <li><a href="/login">Online Rent Ledger</a></li>
                    <li><a href="/login">AI Repair Ticket Dispatch</a></li>
                    <li><a href="/login">Emergency SOS Alarm</a></li>
                    <li><a href="/login">Smart QR Gate Authentication</a></li>
                </ul>
            </div>

            <div class="footer-col-nav">
                <h4>Hours & Curfew</h4>
                <div class="footer-hours-card">
                    <div style="font-weight: 700; color: #E1E0CC; margin-bottom: 0.25rem;">Visiting Hours:</div>
                    <div style="margin-bottom: 0.75rem;"><?= htmlspecialchars($contactInfo['office_hours']) ?></div>
                    <div style="font-weight: 700; color: #10B981; margin-bottom: 0.25rem;">Gate Curfew Policy:</div>
                    <div><?= htmlspecialchars($contactInfo['curfew_hours']) ?></div>
                </div>
            </div>
        </div>

        <div class="footer-inner">
            <div>
                © <?= date('Y') ?> RJM Boardinghouse (MJL Residence). All rights reserved.
            </div>
            <div class="footer-links">
                <a href="#contact">Inquiries & Contact</a>
                <a href="<?= htmlspecialchars($mapData['short_url']) ?>" target="_blank" rel="noopener noreferrer">Google Maps</a>
                <a href="#hero">Back to Top ↑</a>
            </div>
        </div>
    </footer>

    <!-- ================================================================= -->
    <!-- GSAP ANIMATION & INTERACTIVE LOGIC                                -->
    <!-- ================================================================= -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // --- 1. Mobile Menu Toggle ---
            const mobileMenuBtn = document.getElementById('mobile-menu-toggle');
            const mobileDrawer = document.getElementById('mobile-drawer');

            if (mobileMenuBtn && mobileDrawer) {
                mobileMenuBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    mobileDrawer.classList.toggle('open');
                });

                document.addEventListener('click', (e) => {
                    if (!mobileDrawer.contains(e.target) && !mobileMenuBtn.contains(e.target)) {
                        mobileDrawer.classList.remove('open');
                    }
                });

                mobileDrawer.querySelectorAll('a').forEach(link => {
                    link.addEventListener('click', () => {
                        mobileDrawer.classList.remove('open');
                    });
                });
            }

            // --- 2. Room Tier "Inquire" Button Quick Selection ---
            document.querySelectorAll('.btn-tier-inquire').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const roomType = btn.getAttribute('data-room-type');
                    const roomSelect = document.getElementById('inq-room');
                    if (roomSelect && roomType) {
                        for (let i = 0; i < roomSelect.options.length; i++) {
                            if (roomSelect.options[i].text.includes(roomType) || roomSelect.options[i].value === roomType) {
                                roomSelect.selectedIndex = i;
                                break;
                            }
                        }
                    }
                });
            });

            // --- 3. Interactive Room Inquiry AJAX Form Handler ---
            const inquiryForm = document.getElementById('inquiry-form');
            const feedbackEl = document.getElementById('inquiry-feedback');
            const submitBtn = document.getElementById('btn-submit-inquiry');

            if (inquiryForm && feedbackEl) {
                inquiryForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const formData = new FormData(inquiryForm);

                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<span>Transmitting Inquiry...</span>';
                    }

                    try {
                        const response = await fetch('/inquire', {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: formData
                        });

                        const result = await response.json();

                        feedbackEl.style.display = 'block';
                        if (response.ok && result.success) {
                            feedbackEl.className = 'contact-alert success';
                            feedbackEl.innerHTML = `<svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> <span>${String(result.message).replace(/[&<>"']/g, c => '&#' + c.charCodeAt(0) + ';')}</span>`;
                            inquiryForm.reset();
                        } else {
                            feedbackEl.className = 'contact-alert error';
                            feedbackEl.innerHTML = `<svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> <span>${String(result.error || 'Failed to submit inquiry. Please try again or call us directly.').replace(/[&<>"']/g, c => '&#' + c.charCodeAt(0) + ';')}</span>`;
                        }
                    } catch (err) {
                        feedbackEl.style.display = 'block';
                        feedbackEl.className = 'contact-alert error';
                        feedbackEl.innerHTML = `<span>Network connection issue. Please contact our caretaker directly at <?= htmlspecialchars($contactInfo['primary_phone']) ?>.</span>`;
                    } finally {
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<span>Submit Room Inquiry</span> <svg class="icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>';
                        }
                    }
                });
            }

            // --- 4. GSAP Motion Animations ---
            if (window.gsap) {
                if (window.ScrollTrigger) {
                    gsap.registerPlugin(ScrollTrigger);
                }

                // Hero Title Words Pull-Up
                gsap.to(".hero-pull-heading .word-inner", {
                    y: "0%",
                    opacity: 1,
                    duration: 1.1,
                    stagger: 0.12,
                    ease: "power3.out",
                    delay: 0.15
                });

                // Hero Asterisk Pop-In
                gsap.from(".hero-asterisk", {
                    scale: 0,
                    opacity: 0,
                    rotation: 45,
                    duration: 0.8,
                    ease: "back.out(2)",
                    delay: 0.7
                });

                // Hero Subtext & CTA Fade Up
                gsap.from(".hero-fade-sub", {
                    y: 24,
                    opacity: 0,
                    duration: 0.9,
                    ease: "power2.out",
                    delay: 0.4
                });
                gsap.from(".hero-fade-cta", {
                    y: 20,
                    opacity: 0,
                    duration: 0.9,
                    ease: "power2.out",
                    delay: 0.6
                });

                // About Heading Pull-Up on Scroll
                if (window.ScrollTrigger) {
                    gsap.to(".about-heading .word-inner", {
                        scrollTrigger: {
                            trigger: ".about-heading",
                            start: "top 85%",
                            toggleActions: "play none none none"
                        },
                        y: "0%",
                        opacity: 1,
                        duration: 1.0,
                        stagger: 0.06,
                        ease: "power3.out"
                    });

                    // Progressive Character Opacity Reveal
                    const charSpans = document.querySelectorAll("#scroll-char-container .char-reveal");
                    if (charSpans.length > 0) {
                        ScrollTrigger.create({
                            trigger: "#scroll-char-container",
                            start: "top 78%",
                            end: "bottom 38%",
                            scrub: 0.5,
                            onUpdate: (self) => {
                                const progress = self.progress;
                                const total = charSpans.length;
                                const currentTarget = Math.floor(progress * total);
                                charSpans.forEach((span, i) => {
                                    if (i <= currentTarget) {
                                        span.style.opacity = "1";
                                    } else {
                                        span.style.opacity = "0.2";
                                    }
                                });
                            }
                        });
                    }

                    // Bento Cards Staggered Entrance
                    gsap.fromTo(".bento-card",
                        { y: 35, scale: 0.97, opacity: 0 },
                        {
                            scrollTrigger: {
                                trigger: "#features",
                                start: "top 80%",
                                toggleActions: "play none none none"
                            },
                            y: 0,
                            scale: 1,
                            opacity: 1,
                            duration: 0.8,
                            stagger: 0.12,
                            ease: "power2.out",
                            immediateRender: false
                        }
                    );

                    // Room Tier Cards Staggered Entrance
                    gsap.fromTo(".room-tier-card",
                        { y: 30, opacity: 0 },
                        {
                            scrollTrigger: {
                                trigger: "#rooms",
                                start: "top 80%",
                                toggleActions: "play none none none"
                            },
                            y: 0,
                            opacity: 1,
                            duration: 0.8,
                            stagger: 0.15,
                            ease: "power2.out",
                            immediateRender: false
                        }
                    );

                    // Contact Cards Staggered Entrance
                    gsap.fromTo(".channel-card",
                        { x: -20, opacity: 0 },
                        {
                            scrollTrigger: {
                                trigger: "#contact",
                                start: "top 80%",
                                toggleActions: "play none none none"
                            },
                            x: 0,
                            opacity: 1,
                            duration: 0.7,
                            stagger: 0.1,
                            ease: "power2.out",
                            immediateRender: false
                        }
                    );
                }
            } else {
                // Immediate fallback if GSAP is unavailable
                document.querySelectorAll(".word-inner").forEach(el => {
                    el.style.transform = "none";
                    el.style.opacity = "1";
                });
                document.querySelectorAll(".char-reveal").forEach(el => {
                    el.style.opacity = "1";
                });
                document.querySelectorAll(".bento-card").forEach(el => {
                    el.style.opacity = "1";
                    el.style.transform = "none";
                });
                document.querySelectorAll(".room-tier-card").forEach(el => {
                    el.style.opacity = "1";
                });
            }
        });
    </script>
</body>
</html>
