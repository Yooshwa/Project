<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sweetkart - Order Delicious Cakes & Sweets</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #fff5f7 0%, #ffe8ec 100%);
            min-height: 100vh;
            color: #5a3e36;
        }

        nav {
            background: rgba(255, 255, 255, 0.95);
            padding: 1rem 5%;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            color: #ff6b9d;
            text-decoration: none;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            list-style: none;
        }

        .nav-links a {
            text-decoration: none;
            color: #5a3e36;
            font-weight: 500;
            transition: color 0.3s;
            padding: 0.5rem 0;
        }

        .nav-links a:hover {
            color: #ff6b9d;
        }

        .nav-links a.active {
            color: #ff6b9d;
            border-bottom: 2px solid #ff6b9d;
        }

        .menu-toggle {
            display: none;
            flex-direction: column;
            cursor: pointer;
            gap: 5px;
        }

        .menu-toggle span {
            width: 25px;
            height: 3px;
            background: #5a3e36;
            border-radius: 3px;
            transition: 0.3s;
        }

        .hero-section {
            max-width: 1200px;
            margin: 0 auto;
            padding: 5rem 5%;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: calc(100vh - 80px);
            text-align: center;
        }

        .hero-content {
            max-width: 700px;
        }

        .hero-content h1 {
            font-size: 3rem;
            color: #5a3e36;
            margin-bottom: 1.5rem;
            line-height: 1.2;
        }

        .hero-content .tagline {
            font-size: 1.3rem;
            color: #7a5f57;
            margin-bottom: 2.5rem;
            line-height: 1.6;
        }

        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
            color: white;
            padding: 1rem 3rem;
            border-radius: 50px;
            text-decoration: none;
            font-size: 1.1rem;
            font-weight: 600;
            box-shadow: 0 5px 20px rgba(255, 107, 157, 0.3);
            transition: all 0.3s;
        }

        .cta-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(255, 107, 157, 0.4);
        }

        .features {
            display: flex;
            gap: 2rem;
            margin-top: 4rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .feature-item {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
            max-width: 200px;
        }

        .feature-item h3 {
            color: #ff6b9d;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }

        .feature-item p {
            color: #7a5f57;
            font-size: 0.9rem;
        }

        .why-section {
            background: white;
            padding: 4rem 5%;
            margin-top: 2rem;
        }

        .why-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .why-section h2 {
            text-align: center;
            color: #5a3e36;
            font-size: 2.2rem;
            margin-bottom: 3rem;
        }

        .why-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .why-card {
            background: linear-gradient(135deg, #fff5f7 0%, #ffe8ec 100%);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
        }

        .why-card h3 {
            color: #ff6b9d;
            font-size: 1.3rem;
            margin-bottom: 1rem;
        }

        .why-card p {
            color: #7a5f57;
            line-height: 1.7;
        }

        footer {
            background: white;
            padding: 2rem 5%;
            text-align: center;
            margin-top: 3rem;
            border-top: 1px solid #ffe8ec;
        }

        footer p {
            color: #7a5f57;
            font-size: 0.95rem;
        }

        footer p:first-child {
            font-weight: 600;
            color: #5a3e36;
            margin-bottom: 0.5rem;
        }

        @media (max-width: 768px) {
            .menu-toggle {
                display: flex;
            }

            .nav-links {
                position: absolute;
                top: 100%;
                left: 0;
                width: 100%;
                background: white;
                flex-direction: column;
                padding: 1rem;
                gap: 0;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
                display: none;
            }

            .nav-links.active {
                display: flex;
            }

            .nav-links a {
                padding: 1rem;
                border-bottom: 1px solid #ffe8ec;
            }

            .hero-content h1 {
                font-size: 2rem;
            }

            .hero-content .tagline {
                font-size: 1.1rem;
            }

            .hero-section {
                padding: 3rem 5%;
            }

            .why-section h2 {
                font-size: 1.8rem;
            }

            .why-cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav>
        <div class="nav-container">
            <a href="index.php" class="logo">Sweetkart</a>
            <div class="menu-toggle" onclick="toggleMenu()">
                <span></span>
                <span></span>
                <span></span>
            </div>
            <ul class="nav-links" id="navLinks">
                <li><a href="index.php" class="active">Home</a></li>
                <li><a href="about.html">About</a></li>
                <li><a href="auth/signin.html">Sign In</a></li>
            </ul>
        </div>
    </nav>

    <section class="hero-section">
        <div class="hero-content">
            <h1>Welcome to Sweetkart</h1>
            <p class="tagline">
                Order delicious cakes & sweets from trusted vendors near you. 
                Fresh, homemade, and delivered with love.
            </p>
            <a href="auth/signin.html" class="cta-button">Get Started</a>

            <div class="features">
                <div class="feature-item">
                    <h3>🎂 Fresh</h3>
                    <p>Baked fresh daily by local vendors</p>
                </div>
                <div class="feature-item">
                    <h3>🚚 Fast</h3>
                    <p>Quick delivery to your doorstep</p>
                </div>
                <div class="feature-item">
                    <h3>💝 Quality</h3>
                    <p>Trusted vendors with great reviews</p>
                </div>
            </div>
        </div>
    </section>

    <section class="why-section">
        <div class="why-container">
            <h2>Why Choose Sweetkart</h2>
            <div class="why-cards">
                <div class="why-card">
                    <h3>🏪 Support Local Artisans</h3>
                    <p>Every order supports passionate local bakers in your community. We believe in celebrating homegrown talent and helping small businesses thrive while bringing authentic flavors to your table.</p>
                </div>
                <div class="why-card">
                    <h3>🌟 Curated Quality</h3>
                    <p>We carefully select vendors who share our commitment to excellence. Each baker is vetted for quality, hygiene, and craftsmanship, ensuring you receive treats that look as good as they taste.</p>
                </div>
                <div class="why-card">
                    <h3>💖 Made with Love</h3>
                    <p>From birthday celebrations to simple evening cravings, we understand that sweets bring joy. Our platform makes it easy to find the perfect treat for any moment, delivered fresh and with care.</p>
                </div>
            </div>
        </div>
    </section>

    <footer>
        <p>Sweetkart</p>
        <p>© 2025 Sweetkart. All rights reserved. Indulge responsibly.</p>
    </footer>

    <script>
        function toggleMenu() {
            const navLinks = document.getElementById('navLinks');
            navLinks.classList.toggle('active');
        }
    </script>
</body>
</html>