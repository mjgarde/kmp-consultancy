<?php
$roleSessions = [
    'admin'      => 'ADMIN_SESSION',
    'manager'    => 'MANAGER_SESSION',
    'supervisor' => 'SUPERVISOR_SESSION',
    'staff'      => 'STAFF_SESSION',
];

function dashboardForRole(string $role): string
{
    switch ($role) {
        case 'admin':
            return 'admin/dashboard.php';
        case 'manager':
            return 'manager/dashboard.php';
        case 'supervisor':
            return 'supervisor/dashboard.php';
        case 'staff':
            return 'staff/dashboard.php';
        default:
            return '';
    }
}

$logoTarget = null;

foreach ($roleSessions as $role => $sessionName) {
    session_name($sessionName);

    if (isset($_COOKIE[$sessionName])) {
        session_id($_COOKIE[$sessionName]);
    }

    session_start();
    $loggedIn = isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === $role;
    session_write_close();

    if ($loggedIn) {
        $logoTarget = dashboardForRole($role);
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>KMP ConsultHub</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Caladea:ital,wght@0,400;0,700;1,400&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: {
      extend: {
        colors: {
          ink: '#05070C',
          surface: '#0F131C',
          charcoal: '#AFB6C4',
          gold: '#C9A15C',
          goldlight: '#E0BE87',
          paper: '#0A0E16',
          hairline: '#232838'
        },
        fontFamily: {
          serif: ['Caladea', 'serif'],
          sans: ['Roboto', 'sans-serif']
        }
      }
    }
  }
</script>
<style>
  html { scroll-behavior: smooth; }
  body { font-family: 'Roboto', sans-serif; }
  .hero-bg {
    background-image: linear-gradient(180deg, rgba(5,7,12,0.6) 0%, rgba(5,7,12,0.8) 60%, rgba(5,7,12,0.95) 100%), url('assets/img/team/landing.png');
    background-position: center;
    background-size: cover;
    background-repeat: no-repeat;
  }
  #navMenu { transition: max-height 0.35s ease; }
  a:focus-visible, button:focus-visible, input:focus-visible {
    outline: 2px solid #C9A15C;
    outline-offset: 2px;
  }
  .fade-up {
    opacity: 0;
    transform: translateY(28px);
    transition: opacity 0.7s ease, transform 0.7s ease;
  }
  .fade-up.is-visible {
    opacity: 1;
    transform: translateY(0);
  }
  .stagger > * {
    opacity: 0;
    transform: translateY(24px);
    transition: opacity 0.45s ease, transform 0.45s ease;
  }
  .stagger.is-visible > * {
    opacity: 1;
    transform: translateY(0);
  }
  .stagger.is-visible > *:nth-child(1) { transition-delay: 0s; }
  .stagger.is-visible > *:nth-child(2) { transition-delay: .04s; }
  .stagger.is-visible > *:nth-child(3) { transition-delay: .08s; }
  .stagger.is-visible > *:nth-child(4) { transition-delay: .12s; }
  .stagger.is-visible > *:nth-child(5) { transition-delay: .16s; }
  .stagger.is-visible > *:nth-child(6) { transition-delay: .20s; }
  .stagger.is-visible > *:nth-child(7) { transition-delay: .24s; }
  .stagger.is-visible > *:nth-child(8) { transition-delay: .28s; }
  .stagger.is-visible > *:nth-child(9) { transition-delay: .32s; }
  .stagger.is-visible > *:nth-child(10) { transition-delay: .36s; }
  .stagger.is-visible > *:nth-child(11) { transition-delay: .40s; }
  .stagger.is-visible > *:nth-child(12) { transition-delay: .44s; }
  .page {
    transition: opacity 0.35s ease;
  }
</style>
</head>
<body class="bg-paper text-charcoal">

  <nav id="navbar" class="fixed top-0 left-0 w-full z-50 bg-ink/95 backdrop-blur-sm transition-shadow duration-300">
    <div class="max-w-7xl mx-auto flex items-center justify-between gap-6 px-5 sm:px-8 py-3">
      <a href="<?= $logoTarget ? htmlspecialchars($logoTarget) : '#home' ?>" <?= $logoTarget ? '' : 'data-page="home"' ?> class="flex items-center gap-3 shrink-0">
        <img src="assets/img/system_img/logo.png" alt="KMP ConsultHub" class="h-11 w-auto">
        <span class="text-white font-bold text-[15px] leading-tight tracking-wide max-w-[170px]">KMP ConsultHub</span>
      </a>

      <button id="navToggle" aria-label="Toggle menu" aria-expanded="false" class="lg:hidden text-white text-xl p-2 rounded hover:bg-white/10 transition-colors">
        <i class="fa-solid fa-bars"></i>
      </button>

      <ul id="navMenu" class="hidden lg:flex items-center gap-1 absolute lg:static top-full left-0 w-full lg:w-auto bg-ink lg:bg-transparent overflow-hidden lg:overflow-visible">
        <li><a href="#home" data-page="home" class="nav-link block px-4 py-3 lg:py-2 text-white text-[15px] font-semibold hover:bg-white/10 lg:rounded transition-colors border-b border-white/5 lg:border-0">Home</a></li>
        <li><a href="#contact" data-page="contact" class="nav-link block px-4 py-3 lg:py-2 text-white text-[15px] font-medium hover:bg-white/10 lg:rounded transition-colors border-b border-white/5 lg:border-0">Contact</a></li>
        <li><a href="#about" data-page="about" class="nav-link block px-4 py-3 lg:py-2 text-white text-[15px] font-medium hover:bg-white/10 lg:rounded transition-colors">About Us</a></li>
      </ul>
    </div>
  </nav>

  <div id="page-home" class="page" data-page="home">

  <section id="home" class="hero-bg relative min-h-[600px] h-screen w-full flex items-center">
    <div class="max-w-7xl mx-auto w-full px-5 sm:px-8">
      <div class="max-w-2xl">
        <h1 class="font-serif font-bold text-white text-[clamp(2.2rem,6vw,4.5rem)] leading-[1.1] tracking-tight">
          Shaping smarter<br>solutions.
        </h1>
        <div class="w-16 h-[3px] bg-gold my-7"></div>
        <p class="font-serif italic text-white/90 text-[clamp(1.05rem,1.6vw,1.4rem)] leading-relaxed max-w-lg">
          KMP ConsultHub delivers professional consultancy and enterprise training built on results, not guesswork.
        </p>
      </div>
    </div>
  </section>
  <section id="mission-vision" class="bg-paper py-20 sm:py-28 px-5 sm:px-8 border-t border-hairline">
    <div class="max-w-4xl mx-auto">

      <div class="grid grid-cols-1 md:grid-cols-2 gap-12 md:gap-0 md:divide-x md:divide-hairline mb-16 stagger">
        <div class="md:pr-14">
          <h3 class="font-serif font-bold text-xl text-white mb-3">Vision</h3>
          <p class="text-[15px] leading-relaxed text-charcoal/80">
            To be the leading consultancy firm, empowering individuals and organizations with innovative, strategic, and sustainable growth solutions.
          </p>
        </div>
        <div class="md:pl-14">
          <h3 class="font-serif font-bold text-xl text-white mb-3">Mission</h3>
          <p class="text-[15px] leading-relaxed text-charcoal/80">
            We deliver expert consultancy through strategic solutions, training, and data-driven approaches to help clients achieve excellence and lasting growth.
          </p>
        </div>
      </div>

      <div class="pt-14 border-t border-hairline">
        <h3 class="font-serif font-bold text-xl text-white mb-7">Core Values</h3>
        <ul class="space-y-4 max-w-2xl stagger">
          <li class="flex items-start gap-3">
            <span class="mt-[9px] w-1.5 h-1.5 rounded-full bg-gold shrink-0"></span>
            <p class="text-[15px] leading-relaxed text-charcoal/80"><span class="font-semibold text-white">Knowledge</span> — Promoting expertise, learning, and informed decision-making.</p>
          </li>
          <li class="flex items-start gap-3">
            <span class="mt-[9px] w-1.5 h-1.5 rounded-full bg-gold shrink-0"></span>
            <p class="text-[15px] leading-relaxed text-charcoal/80"><span class="font-semibold text-white">Momentum</span> — Driving growth, innovation, and adaptability.</p>
          </li>
          <li class="flex items-start gap-3">
            <span class="mt-[9px] w-1.5 h-1.5 rounded-full bg-gold shrink-0"></span>
            <p class="text-[15px] leading-relaxed text-charcoal/80"><span class="font-semibold text-white">Partnership</span> — Building trust and meaningful client relationships.</p>
          </li>
        </ul>
      </div>

    </div>
  </section>
  <section id="team" class="bg-paper py-20 sm:py-28 px-5 sm:px-8 border-t border-hairline">
    <div class="max-w-6xl mx-auto">

      <div class="text-center mb-16 fade-up">
        <h2 class="font-serif font-bold text-3xl sm:text-4xl text-white">Our Team</h2>
        <div class="flex justify-center gap-2 mt-4">
          <span class="w-2 h-2 rounded-full bg-ink"></span>
          <span class="w-2 h-2 rounded-full bg-ink/50"></span>
          <span class="w-2 h-2 rounded-full bg-ink/20"></span>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-10 gap-y-16">

        <div class="flex flex-col items-center text-center">
          <div class="w-full aspect-[3/4] bg-charcoal/5 rounded overflow-hidden mb-5 flex items-center justify-center">
            <img src="assets/img/team/1.png" alt="Kenjave Mark T. Parlero" class="w-full h-full object-cover" onerror="this.onerror=null;this.style.display='none';this.parentElement.innerHTML='&lt;i class=\'fa-solid fa-user text-4xl text-charcoal/20\'&gt;&lt;/i&gt;'">
          </div>
          <h3 class="font-serif font-bold text-white">Kenjave Mark T. Parlero</h3>
          <div class="w-10 h-px bg-hairline my-2.5"></div>
          <p class="text-xs uppercase tracking-wide text-charcoal/55 mb-3">Chief Advisory Officer / CEO</p>
          <p class="text-sm leading-relaxed text-charcoal/70 max-w-xs">As the Chief Advisory Officer and CEO of KMP Business Consultancy Services, Mr. Parlero is responsible for providing strategic leadership and vision to the company. He oversees the development and execution of company-wide strategies, ensuring alignment with the organization's mission and vision. His role involves representing the company in high-level negotiations and partnerships, cultivating and maintaining key client relationships, and guiding the implementation of long-term plans for sustainable growth. Through his leadership, he drives the achievement of business objectives and positions the company for continued success.</p>
        </div>

        <div class="flex flex-col items-center text-center">
          <div class="w-full aspect-[3/4] bg-charcoal/5 rounded overflow-hidden mb-5 flex items-center justify-center">
            <img src="assets/img/team/2.png" alt="Kenjesan T. Parlero" class="w-full h-full object-cover" onerror="this.onerror=null;this.style.display='none';this.parentElement.innerHTML='&lt;i class=\'fa-solid fa-user text-4xl text-charcoal/20\'&gt;&lt;/i&gt;'">
          </div>
          <h3 class="font-serif font-bold text-white">Kenjesan T. Parlero</h3>
          <div class="w-10 h-px bg-hairline my-2.5"></div>
          <p class="text-xs uppercase tracking-wide text-charcoal/55 mb-3">IT Systems Director &amp; Systems Programmer</p>
          <p class="text-sm leading-relaxed text-charcoal/70 max-w-xs">Kenjesan oversees the company's IT infrastructure and technology strategy, ensuring that all digital initiatives align with business objectives. She leads the implementation of digital transformation projects to enhance operational efficiency, manages cybersecurity and data protection policies, and coordinates IT projects to ensure timely execution. Her role is essential in optimizing IT systems, improving internal operations, and integrating technology solutions that drive business growth.</p>
        </div>

        <div class="flex flex-col items-center text-center">
          <div class="w-full aspect-[3/4] bg-charcoal/5 rounded overflow-hidden mb-5 flex items-center justify-center">
            <img src="assets/img/team/3.png" alt="Dyan Kaye Yaman" class="w-full h-full object-cover" onerror="this.onerror=null;this.style.display='none';this.parentElement.innerHTML='&lt;i class=\'fa-solid fa-user text-4xl text-charcoal/20\'&gt;&lt;/i&gt;'">
          </div>
          <h3 class="font-serif font-bold text-white">Dyan Kaye Yaman</h3>
          <div class="w-10 h-px bg-hairline my-2.5"></div>
          <p class="text-xs uppercase tracking-wide text-charcoal/55 mb-3">Human Resource Consulting Associate</p>
          <p class="text-sm leading-relaxed text-charcoal/70 max-w-xs">Dyan plays a key role in human resource management, assisting clients with recruitment, employee selection, and development. She designs and implements training programs tailored to client needs, advises on HR strategies, and oversees performance evaluations to support employee growth. Additionally, she ensures compliance with HR policies, fosters positive labor relations, and helps create a productive work environment that aligns with industry standards.</p>
        </div>

        <div class="flex flex-col items-center text-center">
          <div class="w-full aspect-[3/4] bg-charcoal/5 rounded overflow-hidden mb-5 flex items-center justify-center">
            <img src="assets/img/team/4.png" alt="Ckyzlle Jayne G. Pebre" class="w-full h-full object-cover" onerror="this.onerror=null;this.style.display='none';this.parentElement.innerHTML='&lt;i class=\'fa-solid fa-user text-4xl text-charcoal/20\'&gt;&lt;/i&gt;'">
          </div>
          <h3 class="font-serif font-bold text-white">Ckyzlle Jayne G. Pebre</h3>
          <div class="w-10 h-px bg-hairline my-2.5"></div>
          <p class="text-xs uppercase tracking-wide text-charcoal/55 mb-3">Financial Consulting Associate</p>
          <p class="text-sm leading-relaxed text-charcoal/70 max-w-xs">CJ is responsible for conducting financial analysis and preparing detailed reports to assist clients in making informed financial decisions. She provides expert advice on budgeting, forecasting, and investment opportunities, ensuring clients develop effective financial strategies. Her role is crucial in supporting business growth initiatives by offering insightful financial planning and analysis, helping clients navigate complex financial landscapes with confidence.</p>
        </div>

        <div class="flex flex-col items-center text-center">
          <div class="w-full aspect-[3/4] bg-charcoal/5 rounded overflow-hidden mb-5 flex items-center justify-center">
            <img src="assets/img/team/5.png" alt="Jane Sweden Suba" class="w-full h-full object-cover" onerror="this.onerror=null;this.style.display='none';this.parentElement.innerHTML='&lt;i class=\'fa-solid fa-user text-4xl text-charcoal/20\'&gt;&lt;/i&gt;'">
          </div>
          <h3 class="font-serif font-bold text-white">Jane Sweden Suba</h3>
          <div class="w-10 h-px bg-hairline my-2.5"></div>
          <p class="text-sm leading-relaxed text-charcoal/70 max-w-xs mt-1">Jane Sweden is a dedicated Business Administration student majoring in Marketing at Goldenstate College of Koronadal. Her educational journey, which includes Maltana National High School and Maltana Elementary School, has provided her with a strong academic foundation. She possesses expertise in leadership, teamwork, and collaboration, complemented by proficiency in computer literacy and time management. Known for her adaptability, flexibility, and professionalism, she is eager to apply her skills and knowledge to contribute effectively to any organization.</p>
        </div>

        <div class="flex flex-col items-center text-center">
          <div class="w-full aspect-[3/4] bg-charcoal/5 rounded overflow-hidden mb-5 flex items-center justify-center">
            <img src="assets/img/team/6.png" alt="Ian Kith S. Parcon" class="w-full h-full object-cover" onerror="this.onerror=null;this.style.display='none';this.parentElement.innerHTML='&lt;i class=\'fa-solid fa-user text-4xl text-charcoal/20\'&gt;&lt;/i&gt;'">
          </div>
          <h3 class="font-serif font-bold text-white">Ian Kith S. Parcon</h3>
          <div class="w-10 h-px bg-hairline my-2.5"></div>
          <p class="text-xs uppercase tracking-wide text-charcoal/55 mb-3">Strategic Communications and Research Manager</p>
          <p class="text-sm leading-relaxed text-charcoal/70 max-w-xs">Mr. Parcon leads the company's communication strategies and public relations efforts, ensuring alignment with business objectives. He oversees market research and analysis to inform strategic decisions and develops effective marketing communication plans. His role involves building and maintaining strong media and public relations partnerships while evaluating the effectiveness of communication campaigns. Through his leadership, he ensures that all messaging remains consistent and supports the company's overall brand and goals.</p>
        </div>

        <div class="flex flex-col items-center text-center">
          <div class="w-full aspect-[3/4] bg-charcoal/5 rounded overflow-hidden mb-5 flex items-center justify-center">
            <img src="assets/img/team/7.png" alt="Dave Ryan Catimbang" class="w-full h-full object-cover" onerror="this.onerror=null;this.style.display='none';this.parentElement.innerHTML='&lt;i class=\'fa-solid fa-user text-4xl text-charcoal/20\'&gt;&lt;/i&gt;'">
          </div>
          <h3 class="font-serif font-bold text-white">Dave Ryan Catimbang</h3>
          <div class="w-10 h-px bg-hairline my-2.5"></div>
          <p class="text-xs uppercase tracking-wide text-charcoal/55 mb-3">Marketing Advisory Associate</p>
          <p class="text-sm leading-relaxed text-charcoal/70 max-w-xs">Dave Ryan Catimbang is responsible for developing and executing marketing strategies for both the company and its clients. He conducts market research and competitive analysis to identify opportunities, assists in brand development and positioning, and manages digital marketing efforts to enhance online presence. Additionally, he measures and analyzes marketing campaign performance to ensure effectiveness, driving brand visibility and client engagement.</p>
        </div>

        <div class="flex flex-col items-center text-center">
          <div class="w-full aspect-[3/4] bg-charcoal/5 rounded overflow-hidden mb-5 flex items-center justify-center">
            <img src="assets/img/team/8.png" alt="Chaeny Lim" class="w-full h-full object-cover" onerror="this.onerror=null;this.style.display='none';this.parentElement.innerHTML='&lt;i class=\'fa-solid fa-user text-4xl text-charcoal/20\'&gt;&lt;/i&gt;'">
          </div>
          <h3 class="font-serif font-bold text-white">Chaeny Lim</h3>
          <div class="w-10 h-px bg-hairline my-2.5"></div>
          <p class="text-xs uppercase tracking-wide text-charcoal/55 mb-3">Chief Legal Counsel</p>
          <p class="text-sm leading-relaxed text-charcoal/70 max-w-xs">Chaeny Lim, as Chief Legal Counsel, ensures that the company operates in full compliance with laws and regulations. He provides expert legal guidance on corporate matters, oversees risk management strategies, and mitigates potential legal issues. His role includes representing the company in legal disputes, managing compliance efforts, and drafting and reviewing contracts and agreements to safeguard the company's interests. Through his expertise, he plays a vital role in protecting and strengthening the company's legal standing.</p>
        </div>

        <div class="flex flex-col items-center text-center">
          <div class="w-full aspect-[3/4] bg-charcoal/5 rounded overflow-hidden mb-5 flex items-center justify-center">
            <img src="assets/img/team/9.png" alt="John Kevin Cubita" class="w-full h-full object-cover" onerror="this.onerror=null;this.style.display='none';this.parentElement.innerHTML='&lt;i class=\'fa-solid fa-user text-4xl text-charcoal/20\'&gt;&lt;/i&gt;'">
          </div>
          <h3 class="font-serif font-bold text-white">John Kevin Cubita</h3>
          <div class="w-10 h-px bg-hairline my-2.5"></div>
          <p class="text-xs uppercase tracking-wide text-charcoal/55 mb-3">Labor Management Associate</p>
          <p class="text-sm leading-relaxed text-charcoal/70 max-w-xs">John Kevin Cubita specializes in advising clients on labor laws and regulations, ensuring compliance with legal standards. He assists in resolving labor disputes, develops fair and effective labor policies, and provides guidance on employee rights and union relations. His role is crucial in helping clients navigate labor relations, conflict resolution, and workplace compliance to foster a fair and legally sound work environment.</p>
        </div>

        <div class="flex flex-col items-center text-center">
          <div class="w-full aspect-[3/4] bg-charcoal/5 rounded overflow-hidden mb-5 flex items-center justify-center">
            <img src="assets/img/team/10.png" alt="Lenith M. Testa" class="w-full h-full object-cover" onerror="this.onerror=null;this.style.display='none';this.parentElement.innerHTML='&lt;i class=\'fa-solid fa-user text-4xl text-charcoal/20\'&gt;&lt;/i&gt;'">
          </div>
          <h3 class="font-serif font-bold text-white">Lenith M. Testa</h3>
          <div class="w-10 h-px bg-hairline my-2.5"></div>
          <p class="text-xs uppercase tracking-wide text-charcoal/55 mb-3">Records Management Associate</p>
          <p class="text-sm leading-relaxed text-charcoal/70 max-w-xs">Lenith oversees the management and organization of the company's records and documentation, ensuring compliance with data protection laws and company policies. She maintains a secure and efficient filing system, facilitates document retrieval and archiving, and monitors records retention to ensure proper disposal. Her role is essential in safeguarding company information and ensuring seamless access to critical documents while adhering to legal and industry standards.</p>
        </div>

        <div class="flex flex-col items-center text-center">
          <div class="w-full aspect-[3/4] bg-charcoal/5 rounded overflow-hidden mb-5 flex items-center justify-center">
            <img src="assets/img/team/11.png" alt="Hanybal T. Bona" class="w-full h-full object-cover" onerror="this.onerror=null;this.style.display='none';this.parentElement.innerHTML='&lt;i class=\'fa-solid fa-user text-4xl text-charcoal/20\'&gt;&lt;/i&gt;'">
          </div>
          <h3 class="font-serif font-bold text-white">Hanybal T. Bona</h3>
          <div class="w-10 h-px bg-hairline my-2.5"></div>
          <p class="text-xs uppercase tracking-wide text-charcoal/55 mb-3">Assistant Systems Programmer</p>
          <p class="text-sm leading-relaxed text-charcoal/70 max-w-xs">Hanybal T. Bona plays an integral role in our technical team as the Assistant Systems Programmer, contributing to the development, testing, and maintenance of software solutions that support seamless business operations. He assists in coding, debugging, and integrating system modules, while ensuring that applications run efficiently and align with organizational requirements. With a dedication to continuous improvement and attention to detail, he helps enhance system performance, streamline processes, and deliver reliable solutions that support the overall success of our projects.</p>
        </div>

        <div class="flex flex-col items-center text-center sm:col-span-2 lg:col-span-1 sm:justify-self-center lg:justify-self-stretch">
          <div class="w-full aspect-[3/4] bg-charcoal/5 rounded overflow-hidden mb-5 flex items-center justify-center">
            <img src="assets/img/team/12.png" alt="Harris Cloyd Tadifa" class="w-full h-full object-cover" onerror="this.onerror=null;this.style.display='none';this.parentElement.innerHTML='&lt;i class=\'fa-solid fa-user text-4xl text-charcoal/20\'&gt;&lt;/i&gt;'">
          </div>
          <h3 class="font-serif font-bold text-white">Harris Cloyd Tadifa</h3>
          <div class="w-10 h-px bg-hairline my-2.5"></div>
          <p class="text-xs uppercase tracking-wide text-charcoal/55 mb-3">General Manager/IT Support</p>
          <p class="text-sm leading-relaxed text-charcoal/70 max-w-xs">Harris Cloyd Tadifa serves as the General Manager and IT Support Lead, overseeing the company's operations, technology systems, and digital transformation initiatives. He manages business processes, supports strategic decision-making, and leads the development and maintenance of digital platforms, websites, and business solutions. Through his leadership and technical expertise, he helps ensure efficient operations, continuous innovation, and exceptional service delivery across the organization.</p>
        </div>

      </div>
    </div>
  </section>

  </div>

  <div id="page-about" class="page hidden" data-page="about">

  <section id="about" class="bg-surface py-20 sm:py-28 px-5 sm:px-8 border-t border-hairline">
    <div class="max-w-5xl mx-auto">

      <div class="text-center mb-16 fade-up">
        <p class="text-xs uppercase tracking-[0.2em] text-charcoal/50 mb-3">About Us</p>
        <h2 class="font-serif font-bold text-3xl sm:text-4xl text-white mb-2">KMP Business Consultancy Services</h2>
        <p class="font-serif italic text-lg text-charcoal/60">Shaping Smarter Solutions.</p>
      </div>

      <div class="max-w-3xl mx-auto mb-16 fade-up">
        <h3 class="font-serif font-bold text-xl text-white mb-4">Corporate Introduction</h3>
        <p class="text-[15px] leading-relaxed text-charcoal/80 mb-4">
          At KMP Business Consultancy Services, we are driven by a commitment to excellence, innovation, and strategic leadership. As a premier consultancy firm, we empower individuals, businesses, and organizations through expert-driven solutions, transformative training, and data-informed strategies that drive sustainable growth and success.
        </p>
        <p class="text-[15px] leading-relaxed text-charcoal/80">
          Guided by our vision to be the leading consultancy firm in delivering innovative, strategic, and sustainable solutions, we work with our clients to navigate challenges, optimize resources, and maximize opportunities in an ever-evolving business landscape.
        </p>
      </div>

      <div class="mb-16">
        <div class="max-w-3xl mx-auto fade-up">
          <h3 class="font-serif font-bold text-xl text-white mb-4">What We Do</h3>
          <p class="text-[15px] leading-relaxed text-charcoal/80 mb-8">
            We offer a wide range of customized consultancy and training services tailored to the unique needs of businesses, government agencies, and organizations. Our core areas of expertise include:
          </p>
        </div>

        <ul class="grid grid-cols-1 md:grid-cols-2 gap-x-10 gap-y-6 max-w-4xl mx-auto stagger">
          <li class="flex items-start gap-3">
            <i class="fa-solid fa-check text-gold mt-1 shrink-0"></i>
            <p class="text-[15px] leading-relaxed text-charcoal/80"><span class="font-semibold text-white">Corporate Management Consulting</span> — Business process optimization, organizational development, and strategic planning to enhance efficiency and productivity.</p>
          </li>
          <li class="flex items-start gap-3">
            <i class="fa-solid fa-check text-gold mt-1 shrink-0"></i>
            <p class="text-[15px] leading-relaxed text-charcoal/80"><span class="font-semibold text-white">Human Resource Management</span> — Recruitment and selection, employee development, performance management, and labor relations support to build a strong and motivated workforce.</p>
          </li>
          <li class="flex items-start gap-3">
            <i class="fa-solid fa-check text-gold mt-1 shrink-0"></i>
            <p class="text-[15px] leading-relaxed text-charcoal/80"><span class="font-semibold text-white">Paralegal and Labor Management Services</span> — Legal documentation, compliance advisory, labor dispute resolution, and policy development to ensure adherence to legal and regulatory requirements.</p>
          </li>
          <li class="flex items-start gap-3">
            <i class="fa-solid fa-check text-gold mt-1 shrink-0"></i>
            <p class="text-[15px] leading-relaxed text-charcoal/80"><span class="font-semibold text-white">Business Feasibility Studies & Market Research</span> — Data-driven insights and market analysis to guide investment decisions, business expansion, and strategic growth.</p>
          </li>
          <li class="flex items-start gap-3">
            <i class="fa-solid fa-check text-gold mt-1 shrink-0"></i>
            <p class="text-[15px] leading-relaxed text-charcoal/80"><span class="font-semibold text-white">Information and Communication Technology Integration</span> — Digital transformation strategies, automation solutions, and tech-driven business optimization to enhance operational efficiency.</p>
          </li>
          <li class="flex items-start gap-3">
            <i class="fa-solid fa-check text-gold mt-1 shrink-0"></i>
            <p class="text-[15px] leading-relaxed text-charcoal/80"><span class="font-semibold text-white">Marketing and Public Relations Consulting</span> — Branding, content strategy, social media management, and reputation building to strengthen market presence.</p>
          </li>
          <li class="flex items-start gap-3">
            <i class="fa-solid fa-check text-gold mt-1 shrink-0"></i>
            <p class="text-[15px] leading-relaxed text-charcoal/80"><span class="font-semibold text-white">Talent and Asset Management</span> — Human capital development, leadership training, and resource optimization to ensure long-term sustainability.</p>
          </li>
          <li class="flex items-start gap-3">
            <i class="fa-solid fa-check text-gold mt-1 shrink-0"></i>
            <p class="text-[15px] leading-relaxed text-charcoal/80"><span class="font-semibold text-white">Professional Training and Development</span> — Capacity-building programs, leadership seminars, and corporate workshops tailored for skill enhancement and organizational growth.</p>
          </li>
        </ul>
      </div>

      <div class="pt-14 border-t border-hairline max-w-3xl mx-auto fade-up">
        <h3 class="font-serif font-bold text-xl text-white mb-4">Our Commitment</h3>
        <p class="text-[15px] leading-relaxed text-charcoal/80 mb-6">
          At KMP Business Consultancy, we uphold our Core Values in everything we do:
        </p>
        <ul class="space-y-4 mb-10 stagger">
          <li class="flex items-start gap-3">
            <span class="mt-[9px] w-1.5 h-1.5 rounded-full bg-gold shrink-0"></span>
            <p class="text-[15px] leading-relaxed text-charcoal/80"><span class="font-semibold text-white">Knowledge</span> — We leverage expertise, continuous learning, and data-driven insights to deliver exceptional results.</p>
          </li>
          <li class="flex items-start gap-3">
            <span class="mt-[9px] w-1.5 h-1.5 rounded-full bg-gold shrink-0"></span>
            <p class="text-[15px] leading-relaxed text-charcoal/80"><span class="font-semibold text-white">Momentum</span> — We embrace adaptability and innovation to propel businesses forward in a competitive landscape.</p>
          </li>
          <li class="flex items-start gap-3">
            <span class="mt-[9px] w-1.5 h-1.5 rounded-full bg-gold shrink-0"></span>
            <p class="text-[15px] leading-relaxed text-charcoal/80"><span class="font-semibold text-white">Partnership</span> — We foster meaningful collaborations built on trust, integrity, and shared success.</p>
          </li>
        </ul>
        <p class="text-[15px] leading-relaxed text-charcoal/80 mb-4">
          With a client-centric approach, we strive to be more than just a consultancy firm—we are a trusted partner in your journey toward achieving excellence.
        </p>
        <p class="text-[15px] leading-relaxed text-charcoal/80">
          Whether you are an entrepreneur, a corporate leader, or a government institution, KMP Business Consultancy Services is here to provide solutions that drive progress and success.
        </p>
      </div>

    </div>
  </section>


  </div>

  <div id="page-contact" class="page hidden" data-page="contact">

  <section id="contact" class="bg-paper py-20 sm:py-28 px-5 sm:px-8">
    <div class="max-w-6xl mx-auto">

      <div class="text-center mb-14 fade-up">
        <h2 class="font-serif font-bold text-3xl sm:text-4xl text-white">Contact</h2>
        <div class="flex justify-center gap-2 mt-4">
          <span class="w-2 h-2 rounded-full bg-ink"></span>
          <span class="w-2 h-2 rounded-full bg-ink/50"></span>
          <span class="w-2 h-2 rounded-full bg-ink/20"></span>
        </div>
      </div>

      <form class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-16 stagger" action="#" method="POST">
        <label class="sr-only" for="contact_name">Name</label>
        <input id="contact_name" type="text" name="contact_name" placeholder="Name" required
          class="w-full px-4 py-3.5 border border-hairline rounded bg-surface text-[15px] focus:border-gold focus:ring-2 focus:ring-gold/20 outline-none transition-colors">

        <label class="sr-only" for="contact_phone">Phone</label>
        <input id="contact_phone" type="text" name="contact_phone" placeholder="Phone"
          class="w-full px-4 py-3.5 border border-hairline rounded bg-surface text-[15px] focus:border-gold focus:ring-2 focus:ring-gold/20 outline-none transition-colors">

        <label class="sr-only" for="contact_email">Email address</label>
        <input id="contact_email" type="email" name="contact_email" placeholder="Email address" required
          class="w-full px-4 py-3.5 border border-hairline rounded bg-surface text-[15px] focus:border-gold focus:ring-2 focus:ring-gold/20 outline-none transition-colors">

        <button type="submit"
          class="w-full px-4 py-3.5 rounded bg-gold text-ink font-semibold text-[15px] hover:bg-goldlight transition-colors">
          Contact Us
        </button>
      </form>

      <div class="grid grid-cols-1 lg:grid-cols-[360px_1fr] gap-6 items-stretch stagger">

        <aside class="bg-surface rounded-lg shadow-[0_10px_30px_rgba(0,0,0,0.08)] border border-hairline p-8 flex flex-col gap-7">

          <div>
            <h5 class="text-white text-xs font-bold tracking-[0.14em] uppercase mb-3">Address</h5>
            <ul class="flex flex-col gap-3">
              <li class="flex items-start gap-3 text-[15px] leading-relaxed">
                <i class="fa-solid fa-location-dot text-white mt-1 w-4 text-center"></i>
                <span>Zone III, City of Koronadal, South Cotabato, Philippines</span>
              </li>
              <li class="flex items-start gap-3 text-[15px] leading-relaxed">
                <i class="fa-solid fa-location-dot text-white mt-1 w-4 text-center"></i>
                <span>Purok Tony Ko, Corner Robredo Street</span>
              </li>
            </ul>
          </div>

          <div>
            <h5 class="text-white text-xs font-bold tracking-[0.14em] uppercase mb-3">Contact</h5>
            <ul class="flex flex-col gap-3">
              <li class="flex items-start gap-3 text-[15px] leading-relaxed">
                <i class="fa-solid fa-phone text-white mt-1 w-4 text-center"></i>
                <a href="tel:+639641351969" class="hover:underline transition-colors">+63-964-135-1969 &nbsp;-&nbsp; Smart</a>
              </li>
              <li class="flex items-start gap-3 text-[15px] leading-relaxed">
                <i class="fa-solid fa-phone text-white mt-1 w-4 text-center"></i>
                <a href="tel:+639929908757" class="hover:underline transition-colors">+63-992-990-8757 &nbsp;-&nbsp; DITO</a>
              </li>
              <li class="flex items-start gap-3 text-[15px] leading-relaxed">
                <i class="fa-solid fa-envelope text-white mt-1 w-4 text-center"></i>
                <a href="mailto:info@kmp-consultancy.com" class="hover:underline transition-colors">info@kmp-consultancy.com</a>
              </li>
            </ul>
          </div>

          <div>
            <h5 class="text-white text-xs font-bold tracking-[0.14em] uppercase mb-3">Office Hours</h5>
            <ul>
              <li class="flex items-start gap-3 text-[15px] leading-relaxed">
                <i class="fa-solid fa-clock text-white mt-1 w-4 text-center"></i>
                <span>8:00 AM - 5:00 PM</span>
              </li>
            </ul>
          </div>

        </aside>

        <div class="rounded-lg overflow-hidden shadow-[0_10px_30px_rgba(0,0,0,0.08)] min-h-[320px] lg:min-h-[480px] bg-hairline">
          <iframe
            class="w-full h-full min-h-[320px] lg:min-h-[480px] border-0 block"
            src="https://www.google.com/maps?q=Zone+III,+City+of+Koronadal,+South+Cotabato,+Philippines&z=15&output=embed"
            allowfullscreen=""
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
            title="KMP ConsultHub Location">
          </iframe>
        </div>

      </div>

    </div>
  </section>

  </div>


  <footer class="bg-ink text-white px-5 sm:px-8 pt-14 pb-6">
    <div class="max-w-6xl mx-auto">

      <div class="grid grid-cols-1 md:grid-cols-2 gap-10 pb-8 border-b border-white/15">

        <div class="flex flex-col gap-4">
          <div class="flex items-center gap-3">
            <img src="assets/img/system_img/logo.png" alt="KMP ConsultHub" class="h-11 w-auto">
            <span class="font-bold text-[15px] leading-tight max-w-[200px]">KMP ConsultHub</span>
          </div>
          <p class="text-sm leading-relaxed text-white/65 max-w-[340px]">
            Shaping smarter solutions through professional consultancy services and enterprise-grade training programs.
          </p>
          <div class="flex gap-3 mt-1">
            <a href="#" aria-label="Facebook" class="w-9 h-9 rounded-full bg-white/10 flex items-center justify-center hover:bg-white hover:text-ink transition-colors">
              <i class="fa-brands fa-facebook-f text-sm"></i>
            </a>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-8">
          <div>
            <h4 class="text-white text-[13px] font-bold tracking-[0.12em] uppercase mb-4">Address</h4>
            <ul class="flex flex-col gap-2.5">
              <li class="text-sm text-white/75 leading-relaxed">Zone III, City of Koronadal, South Cotabato, Philippines</li>
              <li class="text-sm text-white/75 leading-relaxed">Purok Tony Ko, Corner Robredo Street</li>
            </ul>
          </div>

          <div>
            <h4 class="text-white text-[13px] font-bold tracking-[0.12em] uppercase mb-4">Contact</h4>
            <ul class="flex flex-col gap-2.5">
              <li><a href="tel:+639641351969" class="text-sm text-white/75 hover:text-white transition-colors">+63-964-135-1969 - Smart</a></li>
              <li><a href="tel:+639929908757" class="text-sm text-white/75 hover:text-white transition-colors">+63-992-990-8757 - DITO</a></li>
              <li><a href="mailto:info@kmp-consultancy.com" class="text-sm text-white/75 hover:text-white transition-colors">info@kmp-consultancy.com</a></li>
            </ul>
          </div>

          <div>
            <h4 class="text-white text-[13px] font-bold tracking-[0.12em] uppercase mb-4">Office Hours</h4>
            <ul class="flex flex-col gap-2.5">
              <li class="text-sm text-white/75 leading-relaxed">8:00 AM - 5:00 PM</li>
            </ul>
          </div>
        </div>

      </div>

      <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-6 text-[13px] text-white/55 text-center sm:text-left">
        <span>© 2026 KMP ConsultHub. All rights reserved.</span>
        <span>Shaping Smarter Solutions.</span>
      </div>

    </div>
  </footer>

  <script>
    const pages = document.querySelectorAll('.page');
    const pageLinks = document.querySelectorAll('[data-page]');

    function showPage(name) {
      pages.forEach(function (page) {
        if (page.dataset.page === name) {
          page.classList.remove('hidden');
          page.style.opacity = '0';
          requestAnimationFrame(function () {
            page.style.opacity = '1';
          });
        } else {
          page.classList.add('hidden');
          page.style.opacity = '';
        }
      });
      pageLinks.forEach(function (link) {
        link.classList.toggle('text-gold', link.dataset.page === name && link.classList.contains('nav-link'));
      });
      window.scrollTo(0, 0);
      history.replaceState(null, '', '#' + name);
    }

    pageLinks.forEach(function (link) {
      link.addEventListener('click', function (e) {
        e.preventDefault();
        showPage(link.dataset.page);
      });
    });

    const validPages = ['home', 'about', 'contact'];
    const startHash = window.location.hash.replace('#', '');
    showPage(validPages.includes(startHash) ? startHash : 'home');

    const revealObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          revealObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });

    document.querySelectorAll('.fade-up, .stagger').forEach(function (el) {
      revealObserver.observe(el);
    });
  </script>

  <script>
    const navToggle = document.getElementById('navToggle');
    const navMenu = document.getElementById('navMenu');
    const navbar = document.getElementById('navbar');

    navToggle.addEventListener('click', function () {
      const isOpen = navMenu.classList.toggle('flex');
      navMenu.classList.toggle('hidden');
      navMenu.classList.toggle('flex-col');
      navToggle.setAttribute('aria-expanded', navMenu.classList.contains('hidden') ? 'false' : 'true');
      const icon = navToggle.querySelector('i');
      icon.classList.toggle('fa-bars');
      icon.classList.toggle('fa-xmark');
    });

    navMenu.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () {
        if (window.innerWidth < 1024 && !navMenu.classList.contains('hidden')) {
          navMenu.classList.add('hidden');
          navMenu.classList.remove('flex', 'flex-col');
          navToggle.setAttribute('aria-expanded', 'false');
          const icon = navToggle.querySelector('i');
          icon.classList.remove('fa-xmark');
          icon.classList.add('fa-bars');
        }
      });
    });

    window.addEventListener('scroll', function () {
      if (window.scrollY > 20) {
        navbar.classList.add('shadow-lg');
      } else {
        navbar.classList.remove('shadow-lg');
      }
    });
  </script>

</body>
</html>