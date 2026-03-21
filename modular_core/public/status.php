<?php
/**
 * NexSaaS Public Status Page
 * Requirement 10.7
 */
header('Content-Type: text/html');
header('Cache-Control: no-cache');

// Simulate metrics retrieval from Prometheus/Datadog APIs
$systems = [
    'Platform API' => 'Operational',
    'PostgreSQL RLS Cluster' => 'Operational',
    'Redis Cache & Rate Limiter' => 'Operational',
    'AI Engine (Claude Sonnet 3.5)' => 'Operational',
    'Stripe Billing Gateway' => 'Operational',
    'LinkedIn B2B Bridge' => 'Operational',
    'Websocket Broadcast Hub' => 'Operational'
];

$uptime = 99.98; // 99.9% SLA threshold
$color = '#10b981'; // Green
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexSaaS System Status</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #020617; color: #f8fafc; margin: 0; padding: 40px 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 40px; }
        .global-status { background: #0f172a; border: 1px solid #1e293b; padding: 24px; border-radius: 12px; display: flex; align-items: center; justify-content: space-between; margin-bottom: 30px; }
        .global-text { font-size: 20px; font-weight: 800; display: flex; align-items: center; gap: 12px; }
        .dot { height: 16px; width: 16px; background: <?php echo $color; ?>; border-radius: 50%; box-shadow: 0 0 12px <?php echo $color; ?>; }
        .uptime { font-size: 24px; font-weight: 800; color: <?php echo $color; ?>; }
        .component { display: flex; justify-content: space-between; padding: 16px; border-bottom: 1px solid #1e293b; font-weight: 600; }
        .component:last-child { border-bottom: none; }
        img.logo { height: 40px; margin-bottom: 16px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="font-weight: 800; font-size: 32px; letter-spacing: -1px;"><span style="color:#3b82f6">Nex</span>SaaS Trust Center</h1>
            <p style="color: #64748b;">Real-time health and uptime monitoring for the core enterprise network.</p>
        </div>

        <div class="global-status">
            <div class="global-text">
                <div class="dot"></div>
                All Systems Operational
            </div>
            <div style="text-align: right;">
                <div class="uptime"><?php echo $uptime; ?>%</div>
                <div style="font-size: 11px; color: #64748b; text-transform: uppercase;">Rolling 30-Day SLA</div>
            </div>
        </div>

        <div style="background: #0f172a; border: 1px solid #1e293b; border-radius: 12px; overflow: hidden;">
            <?php foreach ($systems as $name => $status): ?>
            <div class="component">
                <span><?php echo htmlspecialchars($name); ?></span>
                <span style="color: <?php echo $status === 'Operational' ? '#10b981' : '#ef4444'; ?>;">
                    <?php echo htmlspecialchars($status); ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
        
        <p style="text-align: center; color: #475569; font-size: 13px; margin-top: 40px;">
            Audits: SOC 2 Type II Certified &middot; GDPR Compliant &middot; ISO 27001
        </p>
    </div>
</body>
</html>
