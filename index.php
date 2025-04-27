<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wall Street Budget - Financial Planning Made Simple</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #0a2e52;
            --secondary-color: #1a5dad;
            --accent-color: #4d96ff;
            --light-accent: #a5c8ff;
            --text-color: #f8f9fa;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
        }
        
        /* Navbar Styles */
        .navbar {
            background-color: var(--primary-color);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 15px 0;
        }
        
        .navbar-brand {
            font-weight: 700;
            color: var(--text-color) !important;
        }
        
        .nav-link {
            color: var(--text-color) !important;
            font-weight: 500;
            margin: 0 10px;
            transition: color 0.3s;
        }
        
        .nav-link:hover {
            color: var(--light-accent) !important;
        }
        
        .btn-outline-light:hover {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
        }
        
        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: var(--text-color);
            padding: 100px 0;
            text-align: center;
        }
        
        .hero h1 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 20px;
        }
        
        .hero p {
            font-size: 1.2rem;
            margin-bottom: 30px;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }
        
        /* Features Section */
        .features {
            padding: 80px 0;
        }
        
        .feature-box {
            background-color: white;
            border-radius: 10px;
            padding: 30px;
            height: 100%;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .feature-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .feature-icon {
            font-size: 2.5rem;
            color: var(--secondary-color);
            margin-bottom: 20px;
        }
        
        /* Testimonials Section */
        .testimonials {
            background-color: #eef4ff;
            padding: 80px 0;
        }
        
        .testimonial-card {
            background-color: white;
            border-radius: 10px;
            padding: 30px;
            height: 100%;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            text-align: center;
        }
        
        .testimonial-avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            margin: 0 auto 20px;
            background-color: var(--light-accent);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
            font-size: 1.8rem;
        }
        
        /* CTA Section */
        .cta {
            padding: 80px 0;
            background: linear-gradient(135deg, var(--secondary-color), var(--accent-color));
            color: var(--text-color);
            text-align: center;
        }
        
        /* Footer */
        .footer {
            background-color: var(--primary-color);
            color: var(--text-color);
            padding: 40px 0 20px;
        }
        
        .footer h5 {
            color: var(--accent-color);
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .footer ul {
            list-style: none;
            padding-left: 0;
        }
        
        .footer ul li {
            margin-bottom: 10px;
        }
        
        .footer ul li a {
            color: var(--text-color);
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .footer ul li a:hover {
            color: var(--light-accent);
            text-decoration: none;
        }
        
        .social-icons a {
            color: var(--text-color);
            font-size: 1.5rem;
            margin-right: 15px;
            transition: color 0.3s;
        }
        
        .social-icons a:hover {
            color: var(--accent-color);
        }
        
        .copyright {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        /* Button Styles */
        .btn-primary {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
            padding: 10px 25px;
            font-weight: 500;
            border-radius: 5px;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-light {
            background-color: white;
            color: var(--primary-color);
            padding: 10px 25px;
            font-weight: 500;
            border-radius: 5px;
        }
        
        .btn-light:hover {
            background-color: var(--light-accent);
            color: var(--primary-color);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-chart-line me-2"></i>Wall Street Budget
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#testimonials">Testimonials</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#pricing">Pricing</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Contact</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <a href="dashboard.php" class="btn btn-light me-2">Dashboard</a>
                        <a href="logout.php" class="btn btn-outline-light">Logout</a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-light me-2">Login</a>
                        <a href="register.php" class="btn btn-outline-light">Sign Up</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <h1>Financial Planning Made Simple</h1>
                    <p>Take control of your personal finances with Wall Street Budget. Track expenses, manage investments, and plan for your financial future with our powerful yet easy-to-use platform.</p>
                    <div class="d-flex justify-content-center">
                        <a href="register.php" class="btn btn-light btn-lg me-3">Get Started Free</a>
                        <a href="#features" class="btn btn-outline-light btn-lg">Learn More</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="container">
            <div class="row mb-5">
                <div class="col-lg-12 text-center">
                    <h2 class="fw-bold">Powerful Features</h2>
                    <p class="lead">Everything you need to manage your finances in one place</p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="feature-box">
                        <div class="feature-icon">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <h4>Expense Tracking</h4>
                        <p>Easily track and categorize your daily expenses. Connect your bank accounts for automatic transaction import or add expenses manually.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-box">
                        <div class="feature-icon">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <h4>Budget Planning</h4>
                        <p>Create custom budgets for different categories and track your progress. Get alerts when you're approaching your spending limits.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-box">
                        <div class="feature-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h4>Investment Tracking</h4>
                        <p>Monitor your investments in stocks, bonds, and other assets. Analyze performance and make informed decisions.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-box">
                        <div class="feature-icon">
                            <i class="fas fa-piggy-bank"></i>
                        </div>
                        <h4>Savings Goals</h4>
                        <p>Set financial goals and track your progress. Whether it's for a vacation, a new car, or retirement, we help you get there.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-box">
                        <div class="feature-icon">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                        <h4>Bill Reminders</h4>
                        <p>Never miss a payment again. Set up reminders for recurring bills and avoid late fees and penalties.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-box">
                        <div class="feature-icon">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <h4>Mobile Access</h4>
                        <p>Access your financial data on the go with our responsive design that works on any device.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="testimonials" id="testimonials">
        <div class="container">
            <div class="row mb-5">
                <div class="col-lg-12 text-center">
                    <h2 class="fw-bold">What Our Users Say</h2>
                    <p class="lead">Join thousands of satisfied users who have transformed their financial lives</p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <div class="testimonial-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <h5>Alex Johnson</h5>
                        <p class="text-muted">Small Business Owner</p>
                        <p>"Wall Street Budget has completely changed how I manage both my personal and business finances. The interface is intuitive and the insights are invaluable."</p>
                        <div class="text-warning">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <div class="testimonial-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <h5>Sarah Williams</h5>
                        <p class="text-muted">Financial Analyst</p>
                        <p>"As someone who works in finance, I have high standards for financial tools. Wall Street Budget exceeds them all with its robust features and clean design."</p>
                        <div class="text-warning">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <div class="testimonial-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <h5>Michael Kamau</h5>
                        <p class="text-muted">Graduate Student</p>
                        <p>"On a tight student budget, this app has been a lifesaver. I've paid off debt and started saving for the future, all thanks to Wall Street Budget."</p>
                        <div class="text-warning">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star-half-alt"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section class="pricing py-5" id="pricing">
        <div class="container">
            <div class="row mb-5">
                <div class="col-lg-12 text-center">
                    <h2 class="fw-bold">Simple, Transparent Pricing</h2>
                    <p class="lead">Choose the plan that fits your financial needs</p>
                </div>
            </div>
            <div class="row g-4 justify-content-center">
                <div class="col-lg-4 col-md-6">
                    <div class="card mb-5 mb-lg-0">
                        <div class="card-body p-5">
                            <h5 class="card-title text-uppercase text-center">Free</h5>
                            <h6 class="card-price text-center">Ksh 0<span class="period">/month</span></h6>
                            <hr>
                            <ul class="fa-ul">
                                <li><span class="fa-li"><i class="fas fa-check"></i></span>Basic Expense Tracking</li>
                                <li><span class="fa-li"><i class="fas fa-check"></i></span>Simple Budget Creation</li>
                                <li><span class="fa-li"><i class="fas fa-check"></i></span>Mobile Access</li>
                                <li class="text-muted"><span class="fa-li"><i class="fas fa-times"></i></span>Investment Tracking</li>
                                <li class="text-muted"><span class="fa-li"><i class="fas fa-times"></i></span>Financial Reports</li>
                                <li class="text-muted"><span class="fa-li"><i class="fas fa-times"></i></span>Bank Connectivity</li>
                            </ul>
                            <div class="d-grid">
                                <a href="register.php" class="btn btn-primary">Sign Up Free</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="card mb-5 mb-lg-0">
                        <div class="card-body p-5">
                            <h5 class="card-title text-uppercase text-center">Pro</h5>
                            <h6 class="card-price text-center">Ksh 999<span class="period">/month</span></h6>
                            <hr>
                            <ul class="fa-ul">
                                <li><span class="fa-li"><i class="fas fa-check"></i></span><strong>Advanced Expense Tracking</strong></li>
                                <li><span class="fa-li"><i class="fas fa-check"></i></span>Unlimited Budgets</li>
                                <li><span class="fa-li"><i class="fas fa-check"></i></span>Investment Tracking</li>
                                <li><span class="fa-li"><i class="fas fa-check"></i></span>Bill Reminders</li>
                                <li><span class="fa-li"><i class="fas fa-check"></i></span>Bank Connectivity</li>
                                <li class="text-muted"><span class="fa-li"><i class="fas fa-times"></i></span>Financial Advisor Access</li>
                            </ul>
                            <div class="d-grid">
                                <a href="register.php?plan=pro" class="btn btn-primary">Get Started</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="card">
                        <div class="card-body p-5">
                            <h5 class="card-title text-uppercase text-center">Premium</h5>
                            <h6 class="card-price text-center">Ksh 1900<span class="period">/month</span></h6>
                            <hr>
                            <ul class="fa-ul">
                                <li><span class="fa-li"><i class="fas fa-check"></i></span><strong>Everything in Pro</strong></li>
                                <li><span class="fa-li"><i class="fas fa-check"></i></span>Advanced Investment Tools</li>
                                <li><span class="fa-li"><i class="fas fa-check"></i></span>Tax Planning Features</li>
                                <li><span class="fa-li"><i class="fas fa-check"></i></span>Financial Reports</li>
                                <li><span class="fa-li"><i class="fas fa-check"></i></span>Financial Advisor Access</li>
                                <li><span class="fa-li"><i class="fas fa-check"></i></span>Priority Support</li>
                            </ul>
                            <div class="d-grid">
                                <a href="register.php?plan=premium" class="btn btn-primary">Get Started</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action Section -->
    <section class="cta">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <h2 class="mb-4">Ready to Take Control of Your Finances?</h2>
                    <p class="mb-4">Join thousands of users who are already achieving their financial goals with Wall Street Budget.</p>
                    <a href="register.php" class="btn btn-light btn-lg">Get Started Today</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer" id="contact">
        <div class="container">
            <div class="row">
                <div class="col-lg-3 col-md-6 mb-4 mb-lg-0">
                    <h5>Wall Street Budget</h5>
                    <p>Financial planning made simple for everyone. Take control of your finances today.</p>
                    <div class="social-icons">
                        <a href="#"><i class="fab fa-facebook"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-linkedin"></i></a>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4 mb-lg-0">
                    <h5>Quick Links</h5>
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="#features">Features</a></li>
                        <li><a href="#pricing">Pricing</a></li>
                        <li><a href="#testimonials">Testimonials</a></li>
                        <li><a href="login.php">Login</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6 mb-4 mb-lg-0">
                    <h5>Resources</h5>
                    <ul>
                        <li><a href="#">Blog</a></li>
                        <li><a href="#">Help Center</a></li>
                        <li><a href="#">FAQs</a></li>
                        <li><a href="#">Security</a></li>
                        <li><a href="#">Privacy Policy</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6">
                    <h5>Contact Us</h5>
                    <ul>
                        <li><i class="fas fa-envelope me-2"></i> support@wallstreetbudget.com</li>
                        <li><i class="fas fa-phone me-2"></i> +254 74 0856 7890</li>
                        <li><i class="fas fa-map-marker-alt me-2"></i> Biashara Street, Nairobi, Kenya</li>
                    </ul>
                </div>
            </div>
            <div class="row copyright">
                <div class="col-lg-12 text-center">
                    <p>&copy; <?php echo date('Y'); ?> Wall Street Budget. All Rights Reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>