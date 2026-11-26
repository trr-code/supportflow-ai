<div class="showroom-home-content">
    <section class="showroom-hero showroom-dark" aria-labelledby="home-heading">
        <div class="showroom-hero-copy">
            <p class="showroom-eyebrow">AI business automation</p>
            <h1 id="home-heading">Less busywork.<br />More business.<br /><span>See AI in action.</span></h1>
            <p class="showroom-hero-description">See how custom AI software can answer customer questions, find answers in your files, and cut invoice data entry.</p>
        </div>
    </section>
    <section id="demos" class="showroom-demo-section" aria-labelledby="demos-heading">
        <h2 id="demos-heading">See where AI can save your team time</h2>
        <p class="showroom-section-description">Open a demo below and follow the <strong class="font-semibold text-[var(--lucid-accent)]">step-by-step instructions</strong> <span class="hidden min-[1100px]:inline">on the right</span><span class="min-[1100px]:hidden">below the demo</span>. Questions about the app? Open the chat bubble and ask Lucid.</p>
        <div class="showroom-demo-cards">
            <a href="{{ route('demo.harbor') }}" wire:navigate class="showroom-demo-card">
                <span class="showroom-card-icon"><x-lucid.icon name="chat" /></span>
                <div>
                    <h3>Customer support</h3>
                    <p>Answer common questions, check the policy behind an answer, request a replacement, or hand a case to a support agent.</p>
                </div>
                <span class="showroom-card-link"
                    >Try demo <x-lucid.icon name="arrow" /><span class="sr-only">: Customer support</span></span
                >
            </a>
            <a href="{{ route('demo.documents') }}" wire:navigate class="showroom-demo-card">
                <span class="showroom-card-icon"><x-lucid.icon name="document" /></span>
                <div>
                    <h3>Document chat</h3>
                    <p>Ask questions about your files and check the passages behind the answers.</p>
                </div>
                <span class="showroom-card-link"
                    >Try demo <x-lucid.icon name="arrow" /><span class="sr-only">: Document chat</span></span
                >
            </a>
            <a href="{{ route('demo.invoices') }}" wire:navigate class="showroom-demo-card">
                <span class="showroom-card-icon"><x-lucid.icon name="invoice-dollar" /></span>
                <div>
                    <h3>Invoice review</h3>
                    <p>Pull details from an invoice, check the results, and export the data.</p>
                </div>
                <span class="showroom-card-link"
                    >Try demo <x-lucid.icon name="arrow" /><span class="sr-only">: Invoice review</span></span
                >
            </a>
        </div>
        <div class="showroom-trust-row" aria-label="Demo safeguards">
            <p><x-lucid.icon name="document" /> Sources you can inspect</p>
            <p><x-lucid.icon name="shield" /> Human approval when needed</p>
            <p><x-lucid.icon name="database" /> Fictional sample data</p>
        </div>
    </section>
</div>
