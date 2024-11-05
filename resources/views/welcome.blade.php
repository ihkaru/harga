<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inflasi Kabupaten Mempawah</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/animejs/3.2.1/anime.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.0/chart.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: #f0f2f5;
        }

        .glow-on-hover {
            width: 220px;
            height: 50px;
            border: none;
            outline: none;
            color: #fff;
            background: #111;
            cursor: pointer;
            position: relative;
            z-index: 0;
            border-radius: 10px;
        }

        .glow-on-hover:before {
            content: '';
            background: linear-gradient(45deg, #ff0000, #ff7300, #fffb00, #48ff00, #00ffd5, #002bff, #7a00ff, #ff00c8, #ff0000);
            position: absolute;
            top: -2px;
            left: -2px;
            background-size: 400%;
            z-index: -1;
            filter: blur(5px);
            width: calc(100% + 4px);
            height: calc(100% + 4px);
            animation: glowing 20s linear infinite;
            opacity: 1;
            transition: opacity .3s ease-in-out;
            border-radius: 10px;
        }

        .glow-on-hover:active {
            color: #000
        }

        .glow-on-hover:active:after {
            background: transparent;
        }

        .glow-on-hover:hover:before {
            opacity: 1;
        }

        .glow-on-hover:after {
            z-index: -1;
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            background: #111;
            left: 0;
            top: 0;
            border-radius: 10px;
        }

        @keyframes glowing {
            0% {
                background-position: 0 0;
            }

            50% {
                background-position: 400% 0;
            }

            100% {
                background-position: 0 0;
            }
        }

        .hero {
            min-height: 100vh;
            background: linear-gradient(135deg, #0a0426 0%, #1a1040 100%);
            color: white;
            padding: 2rem;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('/api/placeholder/1920/1080') center/cover;
            opacity: 0.1;
        }

        .hero-badge {
            background: #ffd700;
            color: #000;
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            margin-bottom: 2rem;
            text-transform: uppercase;
            font-size: 0.9rem;
            opacity: 1;
            transform: translateY(20px);
        }

        .hero-content {
            max-width: 1000px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .hero h1 {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 1;
            transform: translateY(20px);
            line-height: 1.2;
        }

        .hero h1 span {
            color: #b19cd9;
        }

        .hero p {
            font-size: 1.2rem;
            opacity: 1;
            transform: translateY(20px);
            margin-bottom: 2rem;
        }

        .button-container {
            display: flex;
            gap: 1rem;
            justify-content: center;
            opacity: 0;
            transform: translateY(20px);
        }

        .hero-button {
            padding: 0.8rem 2rem;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.1);
            color: white;
            text-decoration: none;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .hero-button:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        /* Sisanya tetap sama seperti sebelumnya */
        .stats-container {
            max-width: 1200px;
            margin: -100px auto 0;
            padding: 0 1rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            position: relative;
            z-index: 2;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            opacity: 0;
            transform: translateY(20px);
        }

        .stat-card h3 {
            color: #333;
            margin-bottom: 0.5rem;
        }

        .stat-card .value {
            font-size: 2rem;
            font-weight: bold;
            color: #1a237e;
        }

        .charts-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 1rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 2rem;
        }

        .chart-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            opacity: 0;
            transform: translateY(20px);
        }

        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.5rem;
            }

            .charts-container {
                grid-template-columns: 1fr;
            }

            .button-container {
                /*flex-direction: column;*/
            }
        }

        /* ... Style sebelumnya tetap sama ... */
        .word-wrapper {
            display: inline-block;
            overflow: hidden;
        }

        .word {
            display: inline-block;
            opacity: 0;
            transform: translateY(100%);
        }

        .char {
            display: inline-block;
            opacity: 0;
            transform: translateY(100%);
        }

        /* ... Style lainnya tetap sama ... */
    </style>
</head>

<body>
    <div class="hero">
        <div class="hero-content">
            <div class="hero-badge">
                <span class="word-wrapper">
                    <span class="word">Sistem</span>
                </span>
                <span class="word-wrapper">
                    <span class="word">Monitoring</span>
                </span>
                <span class="word-wrapper">
                    <span class="word">Inflasi</span>
                </span>
            </div>
            <h1>
                <span class="word-wrapper">
                    <span class="word">Memantau</span>
                </span>
                <span class="word-wrapper">
                    <span class="word">Inflasi,</span>
                </span>
                <br>
                <span class="word-wrapper">
                    <span class="word" style="color: #b19cd9;">Menstabilkan</span>
                </span>
                <span class="word-wrapper">
                    <span class="word" style="color: #b19cd9;">Ekonomi</span>
                </span>
            </h1>
            <p>
                <span class="word-wrapper">
                    <span class="word">Sistem</span>
                </span>
                <span class="word-wrapper">
                    <span class="word">Informasi</span>
                </span>
                <span class="word-wrapper">
                    <span class="word">Terpadu</span>
                </span>
                <span class="word-wrapper">
                    <span class="word">untuk</span>
                </span>
                <span class="word-wrapper">
                    <span class="word">Pemantauan</span>
                </span>
                <span class="word-wrapper">
                    <span class="word">dan</span>
                </span>
                <span class="word-wrapper">
                    <span class="word">Pengendalian</span>
                </span>
                <span class="word-wrapper">
                    <span class="word">Inflasi</span>
                </span>
                <span class="word-wrapper">
                    <span class="word">di</span>
                </span>
                <span class="word-wrapper">
                    <span class="word">Kabupaten</span>
                </span>
                <span class="word-wrapper">
                    <span class="word">Mempawah</span>
                </span>
            </p>
            <div class="button-container">
                <a href="#" class="hero-button glow-on-hover">Data Komoditas</a>
                <a href="#" class="hero-button glow-on-hover">Statistik Inflasi</a>
            </div>
        </div>
    </div>

    <!-- Sisanya tetap sama seperti sebelumnya -->
    <div class="stats-container">
        <div class="stat-card">
            <h3>Tingkat Inflasi</h3>
            <div class="value">4.2%</div>
            <p>YoY September 2024</p>
        </div>
        <div class="stat-card">
            <h3>Inflasi Bulanan</h3>
            <div class="value">0.3%</div>
            <p>MoM September 2024</p>
        </div>
        <div class="stat-card">
            <h3>Komoditas Terpengaruh</h3>
            <div class="value">12</div>
            <p>Komoditas</p>
        </div>
    </div>

    <div class="charts-container">
        <div class="chart-card">
            <canvas id="inflationTrend"></canvas>
        </div>
        <div class="chart-card">
            <canvas id="commodityPrices"></canvas>
        </div>
    </div>

    <script>
        // Data dummy untuk grafik
        const months = ['Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep'];

        // Grafik trend inflasi
        new Chart(document.getElementById('inflationTrend'), {
            type: 'line',
            data: {
                labels: months,
                datasets: [{
                    label: 'Tingkat Inflasi (%)',
                    data: [3.8, 3.9, 4.0, 4.1, 4.0, 4.2],
                    borderColor: '#1a237e',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Trend Inflasi 6 Bulan Terakhir'
                    }
                }
            }
        });

        // Grafik harga komoditas
        new Chart(document.getElementById('commodityPrices'), {
            type: 'bar',
            data: {
                labels: ['Beras', 'Telur', 'Minyak Goreng', 'Cabai', 'Daging Ayam'],
                datasets: [{
                    label: 'Perubahan Harga (%)',
                    data: [5.2, 3.8, -2.1, 8.4, 4.2],
                    backgroundColor: [
                        '#1a237e',
                        '#283593',
                        '#3949ab',
                        '#3f51b5',
                        '#5c6bc0'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Perubahan Harga Komoditas Utama'
                    }
                }
            }
        });
        // Animasi per kata untuk hero badge
        anime.timeline({
            easing: 'easeOutExpo',
        })
        .add({
            targets: '.hero-badge .word',
            translateY: [100, 0],
            opacity: [0, 1],
            duration: 800,
            delay: anime.stagger(100),
        })
        .add({
            targets: 'h1 .word',
            translateY: [100, 0],
            opacity: [0, 1],
            duration: 800,
            delay: anime.stagger(100),
        }, '-=400')
        .add({
            targets: 'p .word',
            translateY: [50, 0],
            opacity: [0, 1],
            duration: 800,
            delay: anime.stagger(50),
        }, '-=400')
        .add({
            targets: '.button-container',
            translateY: [20, 0],
            opacity: [0, 1],
            duration: 600,
        }, '-=400');

        // Animasi untuk stat cards
        anime({
            targets: '.stat-card',
            opacity: [0, 1],
            translateY: [20, 0],
            duration: 800,
            delay: anime.stagger(100),
            easing: 'easeOutExpo'
        });

        // Animasi untuk chart cards
        anime({
            targets: '.chart-card',
            opacity: [0, 1],
            translateY: [20, 0],
            duration: 800,
            delay: anime.stagger(100),
            easing: 'easeOutExpo'
        });

        // ... Kode Chart.js tetap sama ...
    </script>
</body>

</html>
