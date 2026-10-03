<?php

namespace App\Support;

/**
 * The one list of pages. The sidebar renders it and the assistant searches it,
 * so the two can never disagree.
 *
 * roles = who is offered the page; must stay inside the route's RoleMiddleware
 *         list in public/index.php (the route is still what enforces access)
 * nav   = sidebar section per role; a role in `roles` without an entry here
 *         reaches the page through the assistant only
 * words = what people type when they mean this page (English, Tagalog, Bisaya)
 *
 * Order matters: it is the sidebar order, and it breaks ties in find().
 */
final class NavRegistry
{
    private const PAGES = [
        ['href' => '/admin/dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard',
            'roles' => ['admin'], 'nav' => ['admin' => 'Overview'],
            'words' => ['home', 'overview', 'summary', 'main page'],
            'about' => ['en' => "Your dashboard shows today's occupancy, collections, open repairs and active SOS alerts.",
                'tl' => 'Makikita sa dashboard ang occupancy, koleksyon, mga bukas na repair at aktibong SOS alert ngayong araw.']],
        ['href' => '/staff/dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard',
            'roles' => ['staff', 'admin'], 'nav' => ['staff' => 'Overview'],
            'words' => ['home', 'overview', 'sos', 'emergency', 'saklolo'],
            'about' => ['en' => 'The staff dashboard shows active SOS alerts and the work waiting for you.',
                'tl' => 'Makikita sa staff dashboard ang mga aktibong SOS alert at mga gawaing naghihintay sa iyo.']],
        ['href' => '/portal/dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard',
            'roles' => ['boarder'], 'nav' => ['boarder' => 'Overview'],
            'words' => ['home', 'overview', 'balance', 'balanse', 'utang', 'owe', 'sos', 'emergency', 'saklolo', 'tabang'],
            'about' => ['en' => 'Your dashboard shows your room, your balance, your requests and the SOS button.',
                'tl' => 'Makikita sa dashboard mo ang iyong kuwarto, balanse, mga request at ang SOS button.']],
        ['href' => '/admin/inquiry-center', 'label' => 'Inquiry Center', 'icon' => 'inquiry',
            'roles' => ['admin', 'staff'], 'nav' => ['admin' => 'Overview', 'staff' => 'Overview'],
            'words' => ['inquiry', 'inquiries', 'inquire', 'asked about', 'asking about', 'walk-in', 'walk in', 'applicant', 'nagtanong', 'nangutana'],
            'about' => ['en' => 'The Inquiry Center lists people asking about a room, so you can reply and track them.',
                'tl' => 'Nasa Inquiry Center ang mga nagtatanong tungkol sa kuwarto para masagot at masubaybayan mo sila.']],
        ['href' => '/admin/occupancy', 'label' => 'Occupancy', 'icon' => 'occupancy',
            'roles' => ['admin'], 'nav' => ['admin' => 'Overview'],
            'words' => ['occupied', 'how full', 'trend', 'vacancy rate'],
            'about' => ['en' => 'Occupancy shows how full the house is and how that has changed over time.',
                'tl' => 'Ipinapakita ng Occupancy kung gaano kapuno ang bahay at kung paano ito nagbago.']],
        ['href' => '/admin/boarders', 'label' => 'Boarders', 'icon' => 'boarders',
            'roles' => ['admin'], 'nav' => ['admin' => 'Boarding'],
            'words' => ['boarder', 'resident', 'tenant', 'move in', 'move out', 'archive', 'nangungupahan'],
            'about' => ['en' => 'Boarders is the list of residents: add one, open a profile, assign a bed, or archive.',
                'tl' => 'Nasa Boarders ang listahan ng mga nangungupahan: magdagdag, buksan ang profile, magtalaga ng higaan, o i-archive.']],
        ['href' => '/admin/rooms', 'label' => 'Rooms & Beds', 'icon' => 'rooms',
            'roles' => ['admin'], 'nav' => ['admin' => 'Boarding'],
            'words' => ['rooms and beds', 'room', 'bed', 'vacant', 'available bed', 'kwarto', 'kuwarto', 'higaan', 'bakante'],
            'about' => ['en' => 'Rooms & Beds shows every room, its beds and which ones are free.',
                'tl' => 'Makikita sa Rooms & Beds ang bawat kuwarto, mga higaan nito at kung alin ang bakante.']],
        ['href' => '/admin/staff', 'label' => 'Staff Accounts', 'icon' => 'boarders',
            'roles' => ['admin'], 'nav' => ['admin' => 'Boarding'],
            'words' => ['staff', 'staff account', 'employee', 'caretaker', 'deactivate'],
            'about' => ['en' => 'Staff Accounts is where you add staff and turn their access on or off.',
                'tl' => 'Sa Staff Accounts ka nagdadagdag ng staff at nagbubukas o nagsasara ng kanilang access.']],
        ['href' => '/admin/payments', 'label' => 'Payments', 'icon' => 'payments',
            'roles' => ['admin'], 'nav' => ['admin' => 'Finance'],
            'words' => ['payment', 'receipt', 'approve', 'flagged', 'pending', 'collection', 'reverse', 'bayad', 'resibo'],
            'about' => ['en' => 'Payments lists every payment with its receipt; approve, reject or reverse them here.',
                'tl' => 'Nasa Payments ang lahat ng bayad at resibo; dito mag-approve, mag-reject o mag-reverse.']],
        ['href' => '/admin/expenses', 'label' => 'Expenses', 'icon' => 'expenses',
            'roles' => ['admin'], 'nav' => ['admin' => 'Finance'],
            'words' => ['expense', 'spending', 'spent', 'cost', 'utilities', 'gastos'],
            'about' => ['en' => 'Expenses is where you record and review what the house spends.',
                'tl' => 'Sa Expenses mo itinatala at tinitingnan ang mga gastos ng bahay.']],
        ['href' => '/admin/penalty-rules', 'label' => 'Penalties', 'icon' => 'penalties',
            'roles' => ['admin'], 'nav' => ['admin' => 'Finance'],
            'words' => ['penalty', 'penalties', 'fine', 'late fee', 'violation', 'multa'],
            'about' => ['en' => 'Penalties holds the penalty rules, issued penalties and the late-fee check.',
                'tl' => 'Nasa Penalties ang mga patakaran sa multa, mga naibigay na multa at ang late-fee check.']],
        ['href' => '/portal/maintenance/new', 'label' => 'Report a Repair', 'icon' => 'maintenance',
            'roles' => ['boarder'], 'nav' => ['boarder' => 'Resident Services'],
            'words' => ['repair', 'maintenance', 'broken', 'fix', 'leak', 'damage', 'not working', 'clogged',
                'sira', 'nasira', 'guba', 'naguba', 'tulo', 'ayusin', 'pagawa', 'barado'],
            'about' => ['en' => 'Report a Repair is where you tell staff what is broken and add a photo.',
                'tl' => 'Sa Report a Repair mo sasabihin sa staff kung ano ang sira at makakapaglagay ka ng litrato.']],
        ['href' => '/portal/payments/new', 'label' => 'Pay Rent', 'icon' => 'payments',
            'roles' => ['boarder'], 'nav' => ['boarder' => 'Resident Services'],
            'words' => ['pay', 'payment', 'rent', 'receipt', 'gcash', 'bayad', 'bayar', 'renta', 'upa', 'abang', 'resibo'],
            'about' => ['en' => 'Pay Rent is where you send a payment and upload your receipt. An admin checks it before it counts.',
                'tl' => 'Sa Pay Rent ka magpapadala ng bayad at mag-a-upload ng resibo. Sinusuri muna ito ng admin bago mabilang.']],
        ['href' => '/staff/maintenance', 'label' => 'Maintenance Queue', 'icon' => 'maintenance',
            'roles' => ['staff', 'admin'], 'nav' => ['staff' => 'Active Tasks'],
            'words' => ['maintenance', 'queue', 'open repair', 'pending repair', 'ticket', 'repair request', 'sira'],
            'about' => ['en' => 'The Maintenance Queue lists open repair requests, most urgent first.',
                'tl' => 'Nasa Maintenance Queue ang mga bukas na repair request, pinakaapurahan ang una.']],
        ['href' => '/staff/incidents', 'label' => 'Incidents', 'icon' => 'incidents',
            'roles' => ['staff', 'admin', 'boarder'], 'nav' => ['staff' => 'Active Tasks', 'boarder' => 'Community'],
            'words' => ['incident', 'complain', 'complaint', 'noise', 'noisy', 'theft', 'stolen', 'fight', 'harass',
                'insidente', 'reklamo', 'sumbong', 'nakaw', 'away'],
            'about' => ['en' => 'Incidents is where incident reports are filed and followed up.',
                'tl' => 'Sa Incidents inihahain at sinusubaybayan ang mga ulat ng insidente.']],
        ['href' => '/staff/maintenance/history', 'label' => 'Maintenance History', 'icon' => 'maintenance',
            'roles' => ['staff', 'admin'], 'nav' => ['admin' => 'Records', 'staff' => 'Records'],
            'words' => ['repair history', 'past repair', 'finished repair', 'resolved repair', 'history'],
            'about' => ['en' => 'Maintenance History lists repairs that are already finished.',
                'tl' => 'Nasa Maintenance History ang mga repair na tapos na.']],
        ['href' => '/staff/incidents/history', 'label' => 'Incident History', 'icon' => 'incidents',
            'roles' => ['staff', 'admin', 'boarder'], 'nav' => ['admin' => 'Records', 'staff' => 'Records'],
            'words' => ['past incident', 'resolved incident', 'history'],
            'about' => ['en' => 'Incident History lists incidents that are already resolved.',
                'tl' => 'Nasa Incident History ang mga insidenteng naresolba na.']],
        ['href' => '/profile', 'label' => 'Profile',
            'roles' => ['admin', 'staff', 'boarder'], 'nav' => [],
            'words' => ['account', 'password', 'my details', 'contact number', 'email'],
            'about' => ['en' => 'Your profile is where you update your details and change your password.',
                'tl' => 'Sa profile mo binabago ang iyong detalye at password.']],
        ['href' => '/notifications', 'label' => 'Notifications',
            'roles' => ['admin', 'staff', 'boarder'], 'nav' => [],
            'words' => ['notification', 'notice', 'abiso'],
            'about' => ['en' => 'Notifications lists everything the system has told you, newest first.',
                'tl' => 'Nasa Notifications ang lahat ng abiso ng system, pinakabago ang una.']],
    ];

    /** Pages this role is offered, in registry order. */
    public static function forRole(string $role): array
    {
        return array_values(array_filter(self::PAGES, fn ($page) => in_array($role, $page['roles'], true)));
    }

    /** @return array<string, list<array{href:string,label:string,icon:string}>> sidebar sections for this role */
    public static function sidebar(string $role): array
    {
        $sections = [];
        foreach (self::PAGES as $page) {
            if (isset($page['nav'][$role])) {
                $sections[$page['nav'][$role]][] = ['href' => $page['href'], 'label' => $page['label'], 'icon' => $page['icon']];
            }
        }
        return $sections;
    }

    /** Pages this role may open that match the text, best first. Empty when nothing matches. */
    public static function find(string $role, string $text): array
    {
        $text = ' ' . trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($text))) . ' ';
        $scored = [];
        foreach (self::forRole($role) as $i => $page) {
            $score = 0;
            foreach ([mb_strtolower($page['label']), ...$page['words']] as $word) {
                $word = trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $word));
                // ponytail: a word matches the start of a typed word ("pay" hits "paying", "payments");
                // words of 5+ letters also match inside one ("bayad" in "magbabayad"). No stemming or
                // typo tolerance: the AI layer (plan phase A4) takes what this misses.
                if (str_contains($text, ' ' . $word) || (mb_strlen($word) >= 5 && str_contains($text, $word))) {
                    $score += substr_count($word, ' ') + 1; // a longer phrase is a more specific match
                }
            }
            if ($score > 0) {
                $scored[] = [$score, -$i, $page];
            }
        }
        rsort($scored);
        return array_column($scored, 2);
    }
}
