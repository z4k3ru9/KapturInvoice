{{--
    Portfolio-style homepage — "Kinetic Obsidian" dark design system, per the
    Google Stitch "TALL IT Services Portfolio" project.
    Content (services/partners/copy) comes from App\Support\Homepage\PortfolioContent,
    keyed by company slug — see that class for why it isn't a DB-editable field.
--}}
@push('head')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500;600&family=Syne:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
@endpush

<div class="bg-surface-canvas text-on-surface font-portfolio-body antialiased selection:bg-primary selection:text-black relative overflow-x-hidden" id="top">
    <header class="w-full fixed top-0 inset-x-0 z-50 px-4 sm:px-8 pt-4 pointer-events-none transition-all duration-300">
        <div class="max-w-7xl mx-auto pointer-events-auto rounded-full bg-surface-card/85 backdrop-blur-2xl border border-secondary/20 shadow-[0_4px_24px_rgba(0,0,0,0.4)] px-6 sm:px-8 h-16 flex items-center justify-between">
            <a href="#top" class="flex items-center gap-3 group">
                @if ($company->logo_path)
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($company->logo_path) }}" alt="{{ $company->name }}" class="w-auto object-contain h-7 sm:h-8">
                @endif
                <div class="flex items-center gap-2">
                    <span class="font-headline text-xs sm:text-sm tracking-widest text-white font-bold uppercase group-hover:text-secondary transition-colors">{{ strtoupper($company->name) }}</span>
                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-secondary shadow-[0_0_8px_#4edea3] animate-pulse"></span>
                </div>
            </a>
            <nav class="flex items-center gap-6 md:gap-8">
                @if (count($portfolio['services']))
                    <a href="#services" class="text-xs font-portfolio-mono tracking-widest uppercase text-on-surface-variant hover:text-secondary font-semibold transition-colors hidden sm:inline">Services</a>
                @endif
                <a href="#contact" class="text-xs font-portfolio-mono tracking-widest uppercase bg-secondary text-black hover:bg-white transition-all px-4 py-2 rounded-full font-semibold inline-flex items-center gap-1.5 shadow-[0_0_16px_rgba(78,222,163,0.35)] border border-secondary/60">
                    <span class="font-bold">Inquire</span>
                    <span class="material-symbols-outlined text-[14px]">arrow_outward</span>
                </a>
            </nav>
        </div>
    </header>

    <main class="w-full relative">
        {{-- Hero --}}
        <section class="relative min-h-[82vh] lg:min-h-[88vh] flex flex-col justify-center pt-28 pb-12 sm:pb-16 overflow-hidden border-b border-outline-subtle" id="hero">
            <div class="absolute inset-0 z-0 pointer-events-none portfolio-tech-grid opacity-40"></div>
            <div class="absolute inset-0 z-0 pointer-events-none flex items-center justify-center overflow-hidden">
                <div class="w-[540px] lg:w-[720px] h-[540px] lg:h-[720px] rounded-full border border-white/[0.03] flex items-center justify-center">
                    <div class="w-[380px] lg:w-[500px] h-[380px] lg:h-[500px] rounded-full border border-dashed border-primary/[0.08] flex items-center justify-center">
                        <div class="portfolio-radar-sweep absolute inset-0 rounded-full bg-gradient-to-tr from-transparent via-transparent to-primary/[0.05]"></div>
                        <div class="w-[240px] lg:w-[320px] h-[240px] lg:h-[320px] rounded-full border border-white/[0.04] flex items-center justify-center">
                            <div class="w-1.5 h-1.5 rounded-full bg-primary/40 portfolio-pulse-glow"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="max-w-7xl mx-auto px-6 lg:px-12 w-full relative z-20">
                <div class="max-w-4xl">
                    <div class="inline-flex items-center gap-3 px-3 py-1.5 border border-outline-subtle bg-surface-card/70 backdrop-blur-md rounded-sm mb-6">
                        <span class="inline-block w-2 h-2 rounded-full bg-primary shadow-[0_0_8px_#4cd7f6]"></span>
                        <span class="font-portfolio-mono text-xs tracking-widest text-on-surface-variant uppercase">{{ $portfolio['eyebrow'] }}</span>
                    </div>
                    <h1 class="font-headline text-4xl sm:text-6xl lg:text-[4.75rem] tracking-tight text-on-surface font-semibold leading-[1.08] uppercase mb-6">
                        {{ $portfolio['headline_lead'] }}
                        @if ($portfolio['headline_tail'])
                            <br class="hidden sm:inline">
                            <span class="text-on-surface-variant font-light">{{ $portfolio['headline_tail'] }}</span>
                        @endif
                    </h1>
                    <p class="font-portfolio-body text-base sm:text-lg text-on-surface-variant font-light max-w-2xl leading-relaxed mb-8">
                        {{ $portfolio['subheadline'] }}
                    </p>
                    <div class="flex flex-wrap items-center gap-5 sm:gap-8">
                        @if (count($portfolio['services']))
                            <a href="#services" class="group relative inline-flex items-center gap-3 bg-surface-card hover:bg-surface-container text-on-surface px-6 py-3.5 border border-outline-subtle hover:border-primary transition-all duration-200">
                                <span class="font-portfolio-mono text-xs tracking-widest uppercase font-medium">Explore our services</span>
                                <span class="material-symbols-outlined text-[16px] text-primary group-hover:translate-y-0.5 transition-transform">arrow_downward</span>
                            </a>
                        @endif
                        <a href="#contact" class="inline-flex items-center gap-2 font-portfolio-mono text-xs tracking-widest uppercase text-on-surface-variant hover:text-primary transition-colors border-b border-transparent hover:border-primary pb-1">
                            <span>Get in touch</span>
                            <span class="material-symbols-outlined text-[14px]">arrow_outward</span>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        {{-- Ticker --}}
        @if (count($portfolio['ticker']))
            <div class="w-full border-b border-outline-subtle bg-surface-card/80 backdrop-blur-md py-4">
                <div class="max-w-7xl mx-auto px-6 lg:px-12 flex flex-wrap items-center gap-4">
                    @foreach ($portfolio['ticker'] as $i => $item)
                        @if ($i > 0)
                            <span class="text-outline-subtle font-portfolio-mono text-xs hidden md:inline">//</span>
                        @endif
                        <span class="font-portfolio-mono text-xs tracking-widest text-on-surface uppercase font-medium">{{ $item }}</span>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Partners --}}
        @if (count($portfolio['partners']))
            <section class="max-w-7xl mx-auto px-6 lg:px-12 py-16 lg:py-20 relative" id="partners">
                <div class="flex flex-col md:flex-row md:items-baseline justify-between mb-10 lg:mb-12 gap-3">
                    <div>
                        <span class="font-portfolio-mono text-xs tracking-widest text-on-surface-variant uppercase block mb-1.5">01 // TECHNOLOGY PARTNERS</span>
                        <h2 class="font-headline text-2xl lg:text-3xl font-medium tracking-tight uppercase text-on-surface">Brands we work with</h2>
                    </div>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                    @foreach ($portfolio['partners'] as $partner)
                        <div class="border border-outline-subtle p-4 flex flex-col justify-center gap-3 h-28 bg-surface-card/40 hover:border-primary/60 hover:bg-surface-card/80 transition-all duration-200 rounded-sm">
                            <span class="font-headline text-sm font-semibold tracking-wider text-on-surface uppercase">{{ $partner['name'] }}</span>
                            <span class="font-portfolio-mono text-[10px] text-primary/70 tracking-widest uppercase">{{ $partner['tag'] }}</span>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Process --}}
        @if (count($portfolio['process']))
            <section class="w-full border-t border-b border-outline-subtle bg-surface-card/30 py-16 lg:py-20">
                <div class="max-w-7xl mx-auto px-6 lg:px-12">
                    <div class="mb-10">
                        <span class="font-portfolio-mono text-xs tracking-widest text-primary uppercase block mb-1.5">HOW WE WORK</span>
                        <h3 class="font-headline text-2xl lg:text-3xl font-medium tracking-tight uppercase text-on-surface">From survey to support</h3>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        @foreach ($portfolio['process'] as $i => $phase)
                            <div class="border border-outline-subtle bg-surface-card/80 p-6 rounded-sm hover:border-primary/60 transition-all duration-300">
                                <span class="font-portfolio-mono text-xs text-primary tracking-widest block mb-4">PHASE {{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                                <h4 class="font-headline text-lg font-medium text-on-surface uppercase mb-2">{{ $phase['title'] }}</h4>
                                <p class="font-portfolio-body text-xs text-on-surface-variant leading-relaxed">{{ $phase['body'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        {{-- Services --}}
        @if (count($portfolio['services']))
            <section class="max-w-7xl mx-auto px-6 lg:px-12 py-16 lg:py-24 relative" id="services">
                <div class="mb-12 lg:mb-14">
                    <span class="font-portfolio-mono text-xs tracking-widest text-on-surface-variant uppercase block mb-1.5">02 // WHAT WE DO</span>
                    <h2 class="font-headline text-2xl lg:text-3xl font-medium tracking-tight uppercase text-on-surface">Our services</h2>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 lg:gap-8">
                    @foreach ($portfolio['services'] as $service)
                        <div class="flex flex-col justify-between p-6 border border-outline-subtle bg-surface-card/50 hover:bg-surface-card/90 hover:border-primary/60 rounded-sm relative transition-all duration-300">
                            <div>
                                <span class="font-portfolio-mono text-xs text-primary tracking-wider block mb-4">{{ $service['tag'] }}</span>
                                <h3 class="font-headline text-xl font-medium text-on-surface uppercase tracking-wide mb-3">{{ $service['title'] }}</h3>
                                <p class="font-portfolio-body text-sm text-on-surface-variant leading-relaxed font-light mb-6">{{ $service['body'] }}</p>
                            </div>
                            <span class="font-portfolio-mono text-[11px] text-on-surface-variant tracking-wider uppercase pt-4 border-t border-outline-subtle/50">{{ $service['foot'] }}</span>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- SLA banner --}}
        <div class="w-full border-t border-b border-outline-subtle bg-surface-card/60 py-10 lg:py-12">
            <div class="max-w-7xl mx-auto px-6 lg:px-12 flex flex-col md:flex-row items-center justify-between gap-6">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full border border-secondary/40 bg-secondary/10 flex items-center justify-center shadow-[0_0_16px_rgba(78,222,163,0.35)] shrink-0">
                        <span class="material-symbols-outlined text-secondary text-2xl">verified_user</span>
                    </div>
                    <div>
                        <h4 class="font-headline text-base font-semibold uppercase text-on-surface tracking-wider">{{ $portfolio['sla_title'] }}</h4>
                        <p class="font-portfolio-mono text-xs text-on-surface-variant">{{ $portfolio['sla_body'] }}</p>
                    </div>
                </div>
                <a href="#contact" class="font-portfolio-mono text-xs tracking-widest uppercase bg-surface-card hover:bg-surface-container text-on-surface px-5 py-3 border border-outline-subtle hover:border-primary transition-all duration-200 inline-flex items-center gap-2">
                    <span>Get in touch</span>
                    <span class="material-symbols-outlined text-[14px] text-primary">arrow_outward</span>
                </a>
            </div>
        </div>

        {{-- Contact --}}
        <section class="max-w-7xl mx-auto px-6 lg:px-12 py-16 lg:py-24 relative" id="contact">
            <div class="max-w-3xl mb-12">
                <span class="font-portfolio-mono text-xs tracking-widest text-on-surface-variant uppercase block mb-3">03 // GET IN TOUCH</span>
                <h2 class="font-headline text-3xl sm:text-4xl lg:text-5xl font-medium tracking-tight text-on-surface uppercase mb-6 leading-tight">
                    Ready to secure your business or upgrade your IT?
                </h2>
                <div class="flex flex-wrap gap-x-8 gap-y-2 font-portfolio-mono text-sm text-on-surface-variant">
                    @if ($company->email)
                        <a href="mailto:{{ $company->email }}" class="inline-flex items-center gap-2 text-on-surface border-b border-primary pb-1 hover:text-primary transition-colors">
                            {{ strtoupper($company->email) }}
                            <span class="material-symbols-outlined text-[16px]">arrow_outward</span>
                        </a>
                    @endif
                    @if ($company->phone)
                        <span>{{ $company->phone }}</span>
                    @endif
                    @if ($company->address_line_1)
                        <span>{{ collect([$company->address_line_1, $company->city, $company->state])->filter()->implode(', ') }}</span>
                    @endif
                </div>
            </div>

            <div class="max-w-xl border border-outline-subtle bg-surface-card/60 rounded-sm p-6 sm:p-8">
                @if ($submitted)
                    <div class="flex items-center gap-3 text-secondary font-portfolio-mono text-sm">
                        <span class="material-symbols-outlined">check_circle</span>
                        Thanks — your message has been sent. We'll get back to you shortly.
                    </div>
                @else
                    <form wire:submit="submit" class="space-y-4">
                        <div>
                            <label class="block font-portfolio-mono text-[11px] tracking-widest uppercase text-on-surface-variant mb-1.5">Name</label>
                            <input type="text" wire:model="name" placeholder="Jane Doe"
                                class="w-full bg-surface-canvas border border-outline-subtle focus:border-primary rounded-sm px-4 py-3 text-on-surface placeholder:text-on-surface-variant/50 font-portfolio-body text-sm outline-none transition-colors">
                            @error('name') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block font-portfolio-mono text-[11px] tracking-widest uppercase text-on-surface-variant mb-1.5">Email</label>
                            <input type="email" wire:model="email" placeholder="jane@example.com"
                                class="w-full bg-surface-canvas border border-outline-subtle focus:border-primary rounded-sm px-4 py-3 text-on-surface placeholder:text-on-surface-variant/50 font-portfolio-body text-sm outline-none transition-colors">
                            @error('email') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block font-portfolio-mono text-[11px] tracking-widest uppercase text-on-surface-variant mb-1.5">Phone (optional)</label>
                            <input type="text" wire:model="phone" placeholder="+62 ..."
                                class="w-full bg-surface-canvas border border-outline-subtle focus:border-primary rounded-sm px-4 py-3 text-on-surface placeholder:text-on-surface-variant/50 font-portfolio-body text-sm outline-none transition-colors">
                            @error('phone') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block font-portfolio-mono text-[11px] tracking-widest uppercase text-on-surface-variant mb-1.5">Message</label>
                            <textarea wire:model="message" rows="4" placeholder="How can we help?"
                                class="w-full bg-surface-canvas border border-outline-subtle focus:border-primary rounded-sm px-4 py-3 text-on-surface placeholder:text-on-surface-variant/50 font-portfolio-body text-sm outline-none transition-colors"></textarea>
                            @error('message') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <button type="submit"
                            class="w-full inline-flex items-center justify-center gap-2 font-portfolio-mono text-xs tracking-widest uppercase bg-secondary text-black hover:bg-white transition-all px-6 py-3.5 rounded-sm font-semibold">
                            Send message
                            <span class="material-symbols-outlined text-[16px]">send</span>
                        </button>
                    </form>
                @endif
            </div>
        </section>
    </main>

    <footer class="w-full border-t border-outline-subtle py-8 bg-surface-canvas/90">
        <div class="max-w-7xl mx-auto px-6 lg:px-12 flex flex-col md:flex-row items-baseline justify-between gap-4">
            <div class="flex items-center gap-4 flex-wrap">
                <span class="font-headline text-xs tracking-widest uppercase text-on-surface font-medium">{{ strtoupper($company->name) }}</span>
                <span class="font-portfolio-mono text-[11px] text-on-surface-variant hidden md:inline">• {{ $portfolio['tagline'] }}</span>
                <span class="font-portfolio-mono text-[11px] text-on-surface-variant">© {{ now()->year }}</span>
            </div>
            <div class="flex items-center gap-6 font-portfolio-mono text-[11px] text-on-surface-variant tracking-wider uppercase">
                @if ($company->city)
                    <span>{{ $company->city }} / Indonesia</span>
                @endif
                <a href="#top" class="group inline-flex items-center gap-1.5 hover:text-primary transition-colors">
                    <span class="tracking-widest">TOP</span>
                    <span class="material-symbols-outlined text-[14px] group-hover:-translate-y-0.5 transition-transform">arrow_upward</span>
                </a>
            </div>
        </div>
    </footer>
</div>
