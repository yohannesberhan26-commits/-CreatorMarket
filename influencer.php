<?php
require_once '../config/db.php';
require_once '../includes/auth_helper.php';

// Authorize role influencer only
require_role('influencer');

$user_id = $_SESSION['user_id'];

try {
    // 1. Fetch Influencer Profile details
    $stmt = $pdo->prepare("SELECT * FROM influencer_profiles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $influencer = $stmt->fetch();
    
    // Auto-create profile if missing
    if (!$influencer) {
        $stmt = $pdo->prepare("INSERT INTO influencer_profiles (user_id, full_name, category) VALUES (?, ?, 'Fashion')");
        $stmt->execute([$user_id, $_SESSION['username']]);
        
        $stmt = $pdo->prepare("SELECT * FROM influencer_profiles WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $influencer = $stmt->fetch();
    }
    
    // 2. Calculate Stats
    // Earnings (completed deals agreed_price sum)
    $stmt = $pdo->prepare("SELECT SUM(agreed_price) as earnings FROM deals WHERE influencer_id = ? AND status = 'completed'");
    $stmt->execute([$user_id]);
    $earnings_data = $stmt->fetch();
    $earnings = floatval($earnings_data['earnings']);
    
    // Active deals count
    $stmt = $pdo->prepare("SELECT COUNT(id) as count FROM deals WHERE influencer_id = ? AND status IN ('pending', 'active')");
    $stmt->execute([$user_id]);
    $active_deals_count = intval($stmt->fetch()['count']);
    
    // Pending applications count
    $stmt = $pdo->prepare("SELECT COUNT(id) as count FROM applications WHERE influencer_id = ? AND status = 'pending'");
    $stmt->execute([$user_id]);
    $pending_apps_count = intval($stmt->fetch()['count']);
    
    // 3. Fetch My Deals
    $stmt = $pdo->prepare("SELECT d.*, c.title as campaign_title, bp.company_name, u.email as brand_email
                          FROM deals d
                          JOIN campaigns c ON d.campaign_id = c.id
                          JOIN business_profiles bp ON d.business_id = bp.user_id
                          JOIN users u ON bp.user_id = u.id
                          WHERE d.influencer_id = ? AND d.status IN ('pending', 'active')
                          ORDER BY d.created_at DESC LIMIT 5");
    $stmt->execute([$user_id]);
    $recent_deals = $stmt->fetchAll();

    // 4. Fetch My Applications
    $stmt = $pdo->prepare("SELECT a.*, c.title as campaign_title, c.deadline, bp.company_name
                          FROM applications a
                          JOIN campaigns c ON a.campaign_id = c.id
                          JOIN business_profiles bp ON c.business_id = bp.user_id
                          WHERE a.influencer_id = ?
                          ORDER BY a.created_at DESC LIMIT 5");
    $stmt->execute([$user_id]);
    $recent_apps = $stmt->fetchAll();
    
} catch (Exception $e) {
    die("Influencer dashboard fetch failed: " . $e->getMessage());
}

require_once '../includes/header.php';
?>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
    
    <!-- Sidebar -->
    <div class="lg:col-span-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/60 rounded-2xl p-6 shadow-xl space-y-6">
        <div class="text-center">
            <div class="relative h-24 w-24 rounded-full overflow-hidden border-2 border-indigo-500 mx-auto shadow-md">
                <?php if (!empty($influencer['profile_picture'])): ?>
                    <img src="<?php echo $base . sanitize($influencer['profile_picture']); ?>" alt="Profile" class="w-full h-full object-cover">
                <?php else: ?>
                    <div class="w-full h-full bg-indigo-100 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold text-xl uppercase">
                        <?php echo substr($_SESSION['username'], 0, 2); ?>
                    </div>
                <?php endif; ?>
            </div>
            <h3 class="text-lg font-bold text-slate-800 dark:text-white mt-4"><?php echo sanitize($influencer['full_name']); ?></h3>
            <span class="text-xs text-slate-400">@<?php echo sanitize($_SESSION['username']); ?></span>
            <div class="mt-3 inline-block bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 text-xs font-semibold py-1 px-3 rounded-full uppercase tracking-wider">
                <?php echo sanitize($influencer['category']); ?>
            </div>
        </div>

        <ul class="space-y-1.5 border-t border-slate-100 dark:border-slate-700/60 pt-4">
            <li>
                <a href="influencer.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-sm font-semibold bg-indigo-600 text-white shadow-md shadow-indigo-600/10">
                    <i class="fa-solid fa-house"></i>
                    <span>Overview</span>
                </a>
            </li>
            <li>
                <a href="../campaigns.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <span>Find Campaigns</span>
                </a>
            </li>
            <li>
                <a href="../deals.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                    <i class="fa-solid fa-handshake"></i>
                    <span>My Deals</span>
                </a>
            </li>
            <li>
                <a href="../profile.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                    <i class="fa-solid fa-user-gear"></i>
                    <span>Edit Profile</span>
                </a>
            </li>
            <li>
                <a href="../logout.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-rose-500 hover:bg-rose-500/5 transition">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content Area -->
    <div class="lg:col-span-3 space-y-8">
        <div>
            <h2 class="text-3xl font-extrabold text-slate-900 dark:text-white">Creator Dashboard</h2>
            <p class="text-slate-500 dark:text-slate-400 mt-1">Here is your current brand sponsorship status.</p>
        </div>
        
        <!-- Stat Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/60 rounded-xl p-5 shadow-lg flex flex-col justify-between">
                <span class="text-xs uppercase font-bold tracking-wider text-slate-400">Total Earnings</span>
                <span class="text-2xl font-black text-slate-800 dark:text-white my-2">$<?php echo number_format($earnings, 2); ?></span>
                <span class="text-[10px] text-emerald-400 flex items-center font-bold">
                    <i class="fa-solid fa-circle-dollar-to-slot mr-1"></i> Released Payments
                </span>
            </div>
            
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/60 rounded-xl p-5 shadow-lg flex flex-col justify-between">
                <span class="text-xs uppercase font-bold tracking-wider text-slate-400">Active Partnerships</span>
                <span class="text-2xl font-black text-slate-800 dark:text-white my-2"><?php echo $active_deals_count; ?></span>
                <span class="text-[10px] text-slate-400 flex items-center font-medium">
                    <i class="fa-solid fa-hourglass-half mr-1"></i> Projects In-Progress
                </span>
            </div>
            
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/60 rounded-xl p-5 shadow-lg flex flex-col justify-between">
                <span class="text-xs uppercase font-bold tracking-wider text-slate-400">Submitted Pitches</span>
                <span class="text-2xl font-black text-slate-800 dark:text-white my-2"><?php echo $pending_apps_count; ?></span>
                <span class="text-[10px] text-slate-400 flex items-center font-medium">
                    <i class="fa-solid fa-paper-plane mr-1"></i> Awaiting Review
                </span>
            </div>
        </div>
        
        <!-- Active Deals Section -->
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/60 rounded-2xl shadow-xl p-6">
            <div class="flex justify-between items-center border-b border-slate-100 dark:border-slate-700/60 pb-3 mb-6">
                <h3 class="text-lg font-bold text-slate-800 dark:text-white">Active Partnerships / Contracts</h3>
                <a href="../deals.php" class="text-xs font-semibold text-indigo-500 hover:underline">View All Contracts <i class="fa-solid fa-arrow-right ml-1"></i></a>
            </div>
            
            <?php if (count($recent_deals) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-700 text-slate-400 font-semibold">
                                <th class="pb-3">Campaign / Sponsorship Title</th>
                                <th class="pb-3">Brand Partner</th>
                                <th class="pb-3">Agreed Price</th>
                                <th class="pb-3">Milestone Status</th>
                                <th class="pb-3">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                            <?php foreach ($recent_deals as $deal): ?>
                                <tr class="text-slate-700 dark:text-slate-200">
                                    <td class="py-4 font-semibold"><?php echo sanitize($deal['campaign_title']); ?></td>
                                    <td class="py-4"><?php echo sanitize($deal['company_name']); ?></td>
                                    <td class="py-4 font-bold text-emerald-500">$<?php echo number_format($deal['agreed_price'], 2); ?></td>
                                    <td class="py-4">
                                        <?php if ($deal['status'] === 'pending'): ?>
                                            <span class="inline-flex px-2 py-1 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-500 border border-amber-500/20">Awaiting Activation</span>
                                        <?php else: ?>
                                            <span class="inline-flex px-2 py-1 rounded-full text-[10px] font-bold bg-indigo-500/10 text-indigo-500 border border-indigo-500/20">Content Creation</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4">
                                        <a href="../deals.php" class="inline-flex items-center justify-center bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-xs font-semibold py-1.5 px-3 rounded-lg transition">Manage</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-slate-400 text-center py-6 text-sm">You have no active sponsorships running at the moment.</p>
            <?php endif; ?>
        </div>

        <!-- Submitted Applications Section -->
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/60 rounded-2xl shadow-xl p-6">
            <div class="flex justify-between items-center border-b border-slate-100 dark:border-slate-700/60 pb-3 mb-6">
                <h3 class="text-lg font-bold text-slate-800 dark:text-white">Recent Submitted Pitches</h3>
                <a href="../campaigns.php" class="text-xs font-semibold text-indigo-500 hover:underline">Explore Campaigns <i class="fa-solid fa-magnifying-glass ml-1"></i></a>
            </div>
            
            <?php if (count($recent_apps) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-700 text-slate-400 font-semibold">
                                <th class="pb-3">Sponsorship Campaign</th>
                                <th class="pb-3">Company</th>
                                <th class="pb-3">Your Bid Offer</th>
                                <th class="pb-3">Pitch Status</th>
                                <th class="pb-3">Deadline</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                            <?php foreach ($recent_apps as $app): ?>
                                <tr class="text-slate-700 dark:text-slate-200">
                                    <td class="py-4">
                                        <a href="../campaign-details.php?id=<?php echo $app['campaign_id']; ?>" class="font-semibold text-indigo-500 hover:underline">
                                            <?php echo sanitize($app['campaign_title']); ?>
                                        </a>
                                    </td>
                                    <td class="py-4"><?php echo sanitize($app['company_name']); ?></td>
                                    <td class="py-4 text-emerald-500 font-bold">$<?php echo number_format($app['bid_amount'], 2); ?></td>
                                    <td class="py-4">
                                        <?php if ($app['status'] === 'pending'): ?>
                                            <span class="inline-flex px-2 py-1 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-500 border border-amber-500/20">Awaiting Review</span>
                                        <?php elseif ($app['status'] === 'negotiating'): ?>
                                            <span class="inline-flex px-2 py-1 rounded-full text-[10px] font-bold bg-indigo-500/10 text-indigo-500 border border-indigo-500/20">Negotiating</span>
                                        <?php elseif ($app['status'] === 'accepted'): ?>
                                            <span class="inline-flex px-2 py-1 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-500 border border-emerald-500/20">Accepted</span>
                                        <?php else: ?>
                                            <span class="inline-flex px-2 py-1 rounded-full text-[10px] font-bold bg-rose-500/10 text-rose-500 border border-rose-500/20">Declined</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 text-xs text-slate-400"><?php echo date('M d, Y', strtotime($app['deadline'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-slate-400 text-center py-6 text-sm">You have not pitched to any campaigns yet.</p>
            <?php endif; ?>
        </div>
        
    </div>
    
</div>

<?php require_once '../includes/footer.php'; ?>
