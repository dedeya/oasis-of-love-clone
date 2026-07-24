<?php
declare(strict_types=1);

$token = isset($_GET['token']) ? (string) $_GET['token'] : '';
$safeToken = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Traffic Dashboard</title>
    <style>
        :root {
            --ink: #081225;
            --ink-soft: #12213f;
            --gold: #cda954;
            --sand: #f4eddf;
            --white: #ffffff;
            --text: #1c2434;
            --muted: #5e6a81;
            --ok: #1f9d55;
            --warn: #c53030;
            --radius: 14px;
            --shadow: 0 12px 36px rgba(8, 18, 37, 0.12);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Lato", "Segoe UI", sans-serif;
            color: var(--text);
            background: linear-gradient(140deg, #f6f1e5 0%, #fff8eb 55%, #f2ebde 100%);
            min-height: 100vh;
        }
        .wrap {
            max-width: 1080px;
            margin: 0 auto;
            padding: 2rem 1rem 3rem;
        }
        .hero {
            background: linear-gradient(140deg, var(--ink), var(--ink-soft));
            color: var(--white);
            border: 1px solid rgba(205, 169, 84, 0.24);
            border-radius: 18px;
            padding: 1.4rem 1.2rem;
            box-shadow: var(--shadow);
            margin-bottom: 1.1rem;
        }
        .hero h1 {
            margin: 0;
            font-size: clamp(1.4rem, 3vw, 2rem);
        }
        .hero p {
            margin: 0.55rem 0 0;
            color: rgba(255, 255, 255, 0.82);
            font-size: 0.95rem;
        }
        .state {
            margin-top: 0.75rem;
            font-size: 0.88rem;
        }
        .state.ok { color: #96f2b7; }
        .state.err { color: #ffd4d4; }
        .cards {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.75rem;
            margin: 1rem 0 1.25rem;
        }
        .card {
            background: var(--white);
            border: 1px solid rgba(8, 18, 37, 0.08);
            border-radius: var(--radius);
            padding: 0.95rem;
            box-shadow: 0 8px 22px rgba(8, 18, 37, 0.08);
        }
        .card .label {
            color: var(--muted);
            font-size: 0.75rem;
            letter-spacing: 0.7px;
            text-transform: uppercase;
            font-weight: 700;
            margin-bottom: 0.35rem;
        }
        .card .value {
            font-size: clamp(1.2rem, 2.2vw, 1.7rem);
            font-weight: 900;
            color: var(--ink);
            line-height: 1.2;
        }
        .card .sub {
            margin-top: 0.2rem;
            color: var(--muted);
            font-size: 0.78rem;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.9rem;
        }
        .panel {
            background: var(--white);
            border: 1px solid rgba(8, 18, 37, 0.08);
            border-radius: var(--radius);
            padding: 1rem;
            box-shadow: 0 8px 22px rgba(8, 18, 37, 0.08);
        }
        .panel h2 {
            margin: 0;
            font-size: 1rem;
            color: var(--ink);
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0.75rem;
            font-size: 0.9rem;
        }
        .table th, .table td {
            text-align: left;
            padding: 0.52rem 0.2rem;
            border-bottom: 1px solid rgba(8, 18, 37, 0.08);
        }
        .table th {
            color: var(--muted);
            font-size: 0.74rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }
        .table td:last-child, .table th:last-child {
            text-align: right;
        }
        .empty {
            margin-top: 0.75rem;
            color: var(--muted);
            font-size: 0.88rem;
        }
        .token-box {
            background: rgba(255, 255, 255, 0.8);
            border: 1px dashed rgba(8, 18, 37, 0.24);
            border-radius: 12px;
            padding: 0.8rem;
            font-size: 0.88rem;
            color: #334155;
            margin-bottom: 1rem;
        }
        .token-box code {
            font-family: "Menlo", "Consolas", monospace;
            background: rgba(8, 18, 37, 0.06);
            border-radius: 6px;
            padding: 0.2rem 0.35rem;
        }
        @media (max-width: 900px) {
            .cards { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 520px) {
            .cards { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <section class="hero">
            <h1>Site Traffic Dashboard</h1>
            <p>Daily, weekly, monthly, yearly traffic and visitor location insights.</p>
            <div id="status" class="state">Loading statistics...</div>
        </section>

        <div id="tokenHint" class="token-box" style="display:none;">
            Add your token to the URL: <code>analytics-dashboard.php?token=YOUR_TOKEN</code>
            <br>
            Set token securely with environment variable <code>SITE_STATS_TOKEN</code>.
        </div>

        <section class="cards" id="periodCards"></section>

        <section class="grid">
            <article class="panel">
                <h2>All-Time Overview</h2>
                <table class="table" id="totalsTable"></table>
            </article>
            <article class="panel">
                <h2>Top Locations (All Time)</h2>
                <table class="table" id="allLocationsTable"></table>
                <div class="empty" id="allLocationsEmpty" style="display:none;">No location data yet.</div>
            </article>
        </section>

        <section class="grid" style="margin-top:0.9rem;" id="periodLocationPanels"></section>
    </div>

    <script>
        (function () {
            const token = '<?php echo $safeToken; ?>';
            const statusEl = document.getElementById('status');
            const tokenHint = document.getElementById('tokenHint');

            if (!token) {
                tokenHint.style.display = 'block';
                statusEl.textContent = 'Token missing.';
                statusEl.className = 'state err';
                return;
            }

            fetch('analytics.php?action=stats&token=' + encodeURIComponent(token), {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('Request failed with status ' + res.status);
                }
                return res.json();
            })
            .then(function (payload) {
                if (!payload || !payload.success || !payload.stats) {
                    throw new Error('Invalid analytics response');
                }
                renderDashboard(payload.stats, payload.generated_at || '');
            })
            .catch(function (err) {
                statusEl.textContent = 'Unable to load stats: ' + err.message;
                statusEl.className = 'state err';
            });

            function renderDashboard(stats, generatedAt) {
                statusEl.textContent = 'Last updated: ' + (generatedAt || 'N/A') + ' UTC';
                statusEl.className = 'state ok';

                renderPeriodCards(stats.periods || {});
                renderTotals(stats.totals || {});
                renderAllLocations(stats.all_time_top_locations || []);
                renderPeriodLocations(stats.periods || {});
            }

            function renderPeriodCards(periods) {
                const order = [
                    { key: 'daily', title: 'Daily' },
                    { key: 'weekly', title: 'Weekly' },
                    { key: 'monthly', title: 'Monthly' },
                    { key: 'yearly', title: 'Yearly' }
                ];
                const container = document.getElementById('periodCards');
                container.innerHTML = order.map(function (item) {
                    const data = periods[item.key] || {};
                    const views = Number(data.page_views || 0);
                    const visitors = Number(data.unique_visitors || 0);
                    return [
                        '<article class="card">',
                        '<div class="label">' + item.title + ' Views</div>',
                        '<div class="value">' + views.toLocaleString() + '</div>',
                        '<div class="sub">' + visitors.toLocaleString() + ' unique visitors</div>',
                        '</article>'
                    ].join('');
                }).join('');
            }

            function renderTotals(totals) {
                const table = document.getElementById('totalsTable');
                const allViews = Number(totals.all_time_page_views || 0).toLocaleString();
                const allVisitors = Number(totals.all_time_unique_visitors || 0).toLocaleString();
                table.innerHTML = [
                    '<tr><th>Metric</th><th>Value</th></tr>',
                    '<tr><td>All-time page views</td><td>' + allViews + '</td></tr>',
                    '<tr><td>All-time unique visitors</td><td>' + allVisitors + '</td></tr>'
                ].join('');
            }

            function renderAllLocations(locations) {
                const table = document.getElementById('allLocationsTable');
                const empty = document.getElementById('allLocationsEmpty');
                if (!locations.length) {
                    table.innerHTML = '';
                    empty.style.display = 'block';
                    return;
                }

                empty.style.display = 'none';
                const rows = locations.map(function (item) {
                    return '<tr><td>' + escapeHtml(item.location || 'Unknown') + '</td><td>' + Number(item.visits || 0).toLocaleString() + '</td></tr>';
                }).join('');
                table.innerHTML = '<tr><th>Location</th><th>Visits</th></tr>' + rows;
            }

            function renderPeriodLocations(periods) {
                const order = [
                    { key: 'daily', title: 'Daily Top Locations' },
                    { key: 'weekly', title: 'Weekly Top Locations' },
                    { key: 'monthly', title: 'Monthly Top Locations' },
                    { key: 'yearly', title: 'Yearly Top Locations' }
                ];
                const container = document.getElementById('periodLocationPanels');
                container.innerHTML = order.map(function (item) {
                    const data = periods[item.key] || {};
                    const locations = Array.isArray(data.top_locations) ? data.top_locations : [];
                    const rows = locations.map(function (entry) {
                        return '<tr><td>' + escapeHtml(entry.location || 'Unknown') + '</td><td>' + Number(entry.visits || 0).toLocaleString() + '</td></tr>';
                    }).join('');

                    const table = locations.length
                        ? '<table class="table"><tr><th>Location</th><th>Visits</th></tr>' + rows + '</table>'
                        : '<div class="empty">No visits in this period yet.</div>';

                    return '<article class="panel"><h2>' + item.title + '</h2>' + table + '</article>';
                }).join('');
            }

            function escapeHtml(value) {
                return String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }
        })();
    </script>
</body>
</html>
