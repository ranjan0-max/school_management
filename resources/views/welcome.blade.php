@extends('layouts.public', [
    'title' => 'A smarter way to run your school',
    'description' => 'One thoughtful platform for academics, attendance, fees, and school operations.',
])

@section('content')
    <section class="hero-section">
        <div class="hero-glow hero-glow-one" aria-hidden="true"></div>
        <div class="hero-glow hero-glow-two" aria-hidden="true"></div>

        <div class="container app-container position-relative">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <div class="eyebrow">
                        <span class="eyebrow-dot"></span>
                        Multi-school SaaS foundation
                    </div>

                    <h1 class="hero-title">
                        School operations,
                        <span>beautifully organized.</span>
                    </h1>

                    <p class="hero-copy">
                        A secure and scalable workspace designed to bring academics, people,
                        attendance, fees, and reporting together without the clutter.
                    </p>

                    <div class="d-flex flex-column flex-sm-row gap-3 hero-actions">
                        <a class="btn btn-primary btn-lg app-btn-primary" href="#foundation">
                            Explore the foundation
                            <span aria-hidden="true">&rarr;</span>
                        </a>
                        <span class="hero-note">
                            <span class="hero-note-icon" aria-hidden="true">✓</span>
                            Laravel and Bootstrap ready
                        </span>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="product-preview" aria-label="Application navigation preview">
                        <div class="preview-topbar">
                            <div class="preview-window-controls" aria-hidden="true">
                                <span></span><span></span><span></span>
                            </div>
                            <span class="preview-school">Greenfield Academy</span>
                            <span class="preview-avatar">RA</span>
                        </div>

                        <div class="preview-body">
                            <aside class="preview-sidebar">
                                <div class="preview-logo-line"></div>
                                <div class="preview-nav-item active"><span></span>Dashboard</div>
                                <div class="preview-label">Management</div>
                                <div class="preview-nav-item"><span></span>Students</div>
                                <div class="preview-nav-item"><span></span>Attendance</div>
                                <div class="preview-nav-item"><span></span>Fees</div>
                                <div class="preview-label">Academics</div>
                                <div class="preview-nav-item"><span></span>Classes</div>
                                <div class="preview-nav-item"><span></span>Examinations</div>
                            </aside>

                            <div class="preview-content">
                                <div class="preview-heading-row">
                                    <div>
                                        <span class="preview-kicker">OVERVIEW</span>
                                        <div class="preview-title-line"></div>
                                    </div>
                                    <div class="preview-button"></div>
                                </div>

                                <div class="preview-stat-grid">
                                    <div class="preview-stat-card accent-purple"><span></span><strong>1,248</strong><small>Students</small></div>
                                    <div class="preview-stat-card accent-blue"><span></span><strong>94.6%</strong><small>Attendance</small></div>
                                    <div class="preview-stat-card accent-green"><span></span><strong>₹8.4L</strong><small>Fees collected</small></div>
                                </div>

                                <div class="preview-chart-card">
                                    <div class="preview-chart-head"><span></span><span></span></div>
                                    <div class="preview-chart-bars" aria-hidden="true">
                                        <i style="height: 38%"></i><i style="height: 52%"></i><i style="height: 45%"></i>
                                        <i style="height: 68%"></i><i style="height: 58%"></i><i style="height: 82%"></i>
                                        <i style="height: 72%"></i><i style="height: 92%"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="foundation" class="foundation-section">
        <div class="container app-container">
            <div class="section-heading mx-auto text-center">
                <span class="section-kicker">BUILT WITH INTENTION</span>
                <h2>A calm interface for complex school work</h2>
                <p>Every part of the platform will follow the same clear, accessible, and responsive visual system.</p>
            </div>

            <div class="row g-4 mt-2">
                <div class="col-md-4">
                    <article class="feature-card h-100">
                        <span class="feature-number">01</span>
                        <h3>Focused navigation</h3>
                        <p>Grouped menus show each person only the tools their school and role allow.</p>
                    </article>
                </div>
                <div class="col-md-4">
                    <article class="feature-card h-100">
                        <span class="feature-number">02</span>
                        <h3>Reliable by design</h3>
                        <p>Tenant-aware access, careful validation, and audit-ready workflows protect school data.</p>
                    </article>
                </div>
                <div class="col-md-4">
                    <article class="feature-card h-100">
                        <span class="feature-number">03</span>
                        <h3>Ready to grow</h3>
                        <p>A modular foundation keeps reports fast and allows new services to arrive without clutter.</p>
                    </article>
                </div>
            </div>
        </div>
    </section>
@endsection
