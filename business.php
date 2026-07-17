<?php
require_once '../config/db.php';
require_once '../includes/auth_helper.php';

// Authorize business accounts only
require_role('business');

$user_id = $_SESSION['user_id'];

try {
    // 1. Fetch Business Profile
    $stmt = $pdo->prepare("SELECT * FROM business_profiles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $business = $stmt->fetch();
    
    // Auto-create profile if missing
    if (!$business) {
        $stmt = $pdo->prepare("INSERT INTO business_profiles (user_id, company_name, industry) VALUES (?, ?, 'Sponsor')");
        $stmt->execute([$user_id, $_SESSION['username']]);
        
        $stmt = $pdo->prepare("SELECT * FROM business_profiles WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $business = $stmt->fetch();
    }
    
    // 2. Fetch Stat Counters
    // Total posted campaigns
    $stmt = $pdo->prepare("SELECT COUNT(id) as count FROM campaigns WHERE business_id = ?");
    $stmt->execute([$user_id]);
    $campaigns_count = intval($stmt->fetch()['count']);
    
    // Total pending applications for this business's campaigns
    $stmt = $pdo->prepare("SELECT COUNT(a.id) as count 
                          FROM applications a 
                          JOIN campaigns c ON a.campaign_id = c.id 
                          WHERE c.business_id = ? AND a.status = 'pending'");
    $stmt->execute([$user_id]);
    $pending_apps_count = intval($stmt->fetch()['count']);
    
    // Total active/pending deals
    $stmt = $pdo->prepare("SELECT COUNT(id) as count FROM deals WHERE business_id = ? AND status IN ('pending', 'active')");
    $stmt->execute([$user_id]);
    $active_deals_count = intval($stmt->fetch()['count']);
    
    // 3. Fetch Posted Campaigns
    // Include applicant counts
    $stmt = $pdo->prepare("SELECT c.*, 
                          (SELECT COUNT(id) FROM applications WHERE campaign_id = c.id) as applicants_count 
                          FROM campaigns c 
                          WHERE c.business_id = ? 
                          ORDER BY c.created_at DESC");
    $stmt->execute([$user_id]);
    $my_campaigns = $stmt->fetchAll();
    
    // 4. Fetch Active Deal Contracts
    // Show influencer/promoter details, clicks, and conversions
    $stmt = $pdo->prepare("SELECT d.*, c.title as campaign_title, u.username, u.role as talent_role,
                          COALESCE(ip.full_name, pp.full_name) as talent_name
                          FROM deals d
                          JOIN campaigns c ON d.campaign_id = c.id
                          JOIN users u ON d.influencer_id = u.id
                          LEFT JOIN influencer_profiles ip ON u.id = ip.user_id
                          LEFT JOIN promoter_profiles pp ON u.id = pp.user_id
                          WHERE d.business_id = ? AND d.status IN ('pending', 'active')
                          ORDER BY d.created_at DESC");
    $stmt->execute([$user_id]);
    $active_deals = $stmt->fetchAll();
    
} catch (Exception $e) {
    die("Business dashboard query failed: " . $e->getMessage());
}

require_once '../includes/header.php';
?>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
    
    <!-- Sidebar -->
    <div class="lg:col-span-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/60 rounded-2xl p-6 shadow-xl space-y-6">
        <div class="text-center">
            <div class="relative h-24 w-24 rounded-lg overflow-hidden border border-slate-200 dark:border-slate-700 mx-auto shadow-md p-2 bg-white flex items-center justify-center">
                <?php if (!empty($business['logo_image'])): ?>
                    <img src="<?php echo $base . sanitize($business['logo_image']); ?>" alt="Company Logo" class="max-h-full max-w-full object-contain">
                <?php else: ?>
                    <div class="w-full h-full bg-indigo-100 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold text-xl uppercase rounded-lg">
                        <?php echo substr($_SESSION['username'], 0, 2); ?>
                    </div>
                <?php endif; ?>
            </div>
            <h3 class="text-lg font-bold text-slate-800 dark:text-white mt-4"><?php echo sanitize($business['company_name']); ?></h3>
            <span class="text-xs text-slate-400">@<?php echo sanitize($_SESSION['username']); ?></span>
            <div class="mt-3 inline-block bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 text-xs font-semibold py-1 px-3 rounded-full uppercase tracking-wider">
                <?php echo sanitize($business['industry']); ?>
            </div>
        </div>

        <ul class="space-y-1.5 border-t border-slate-100 dark:border-slate-700/60 pt-4">
            <li>
                <a href="business.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-sm font-semibold bg-indigo-600 text-white shadow-md shadow-indigo-600/10">
                    <i class="fa-solid fa-house"></i>
                    <span>Overview</span>
                </a>
            </li>
            <li>
                <a href="../campaign-create.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                    <i class="fa-solid fa-circle-plus"></i>
                    <span>Create Campaign</span>
                </a>
            </li>
            <li>
                <a href="../deals.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                    <i class="fa-solid fa-handshake"></i>
                    <span>Manage Contracts</span>
                </a>
            </li>
            <li>
                <a href="../creators.php" class="flex items-center space-x-3 px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <span>Search Creators</span>
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

    <!-- Main Content -->
    <div class="lg:col-span-3 space-y-8">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h2 class="text-3xl font-extrabold text-slate-900 dark:text-white">Brand Hub</h2>
                <p class="text-slate-500 dark:text-slate-400 mt-1">Manage marketing campaigns, hire partners, and audit active contract progress.</p>
            </div>
            <a href="../campaign-create.php" class="inline-flex items-center space-x-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-sm py-2.5 px-5 rounded-lg shadow-lg shadow-indigo-600/20 transition">
                <i class="fa-solid fa-plus"></i>
                <span>Post Campaign</span>
            </a>
        </div>

        <!-- Stat Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/60 rounded-xl p-5 shadow-lg flex flex-col justify-between">
                <span class="text-xs uppercase font-bold tracking-wider text-slate-400">Campaigns Launched</span>
                <span class="text-2xl font-black text-slate-800 dark:text-white my-2"><?php echo $campaigns_count; ?></span>
                <span class="text-[10px] text-slate-400 flex items-center font-medium">
                    <i class="fa-solid fa-bullhorn mr-1"></i> Active listings
                </span>
            </div>

            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/60 rounded-xl p-5 shadow-lg flex flex-col justify-between">
                <span class="text-xs uppercase font-bold tracking-wider text-slate-400">Unreviewed Applicants</span>
                <span class="text-2xl font-black text-slate-800 dark:text-white my-2"><?php echo $pending_apps_count; ?></span>
                <span class="text-[10px] text-indigo-400 flex items-center font-bold">
                    <i class="fa-solid fa-paper-plane mr-1"></i> Awaiting hiring action
                </span>
            </div>

            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/60 rounded-xl p-5 shadow-lg flex flex-col justify-between">
                <span class="text-xs uppercase font-bold tracking-wider text-slate-400">Contracts Running</span>
                <span class="text-2xl font-black text-slate-800 dark:text-white my-2"><?php echo $active_deals_count; ?></span>
                <span class="text-[10px] text-slate-400 flex items-center font-medium">
                    <i class="fa-solid fa-handshake mr-1"></i> Creator partnerships
                </span>
            </div>
        </div>

        <!-- Posted Campaigns Table -->
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/60 rounded-2xl shadow-xl p-6">
            <h3 class="text-lg font-bold text-slate-800 dark:text-white border-b border-slate-100 dark:border-slate-700/60 pb-3 mb-6">Your Posted Campaigns</h3>
            
            <?php if (count($my_campaigns) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-700 text-slate-400 font-semibold">
                                <th class="pb-3">Campaign Sponsorship Title</th>
                                <th class="pb-3">Budget</th>
                                <th class="pb-3">Applicants Count</th>
                                <th class="pb-3">Deadline</th>
                                <th class="pb-3">Listing Status</th>
                                <th class="pb-3 text-right">Details</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                            <?php foreach ($my_campaigns as $camp): ?>
                                <tr class="text-slate-700 dark:text-slate-200 hover:bg-slate-50/50 dark:hover:bg-slate-700/10 transition">
                                    <td class="py-4">
                                        <a href="../campaign-details.php?id=<?php echo $camp['id']; ?>" class="font-bold text-slate-900 dark:text-white hover:text-indigo-500 hover:underline transition">
                                            <?php echo sanitize($camp['title']); ?>
                                        </a>
                                        <span class="block text-xs text-slate-400 mt-0.5"><?php echo sanitize($camp['category']); ?></span>
                                    </td>
                                    <td class="py-4 font-bold text-emerald-500">$<?php echo number_format($camp['budget'], 2); ?></td>
                                    <td class="py-4 font-bold">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-indigo-500/10 text-indigo-500 font-bold border border-indigo-500/15">
                                            <?php echo $camp['applicants_count']; ?> Pitched
                                        </span>
                                    </td>
                                    <td class="py-4 text-xs text-slate-400"><?php echo date('M d, Y', strtotime($camp['deadline'])); ?></td>
                                    <td class="py-4">
                                        <?php if ($camp['status'] === 'active'): ?>
                                            <span class="inline-flex px-2 py-1 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-500 border border-emerald-500/20">Open / Active</span>
                                        <?php else: ?>
                                            <span class="inline-flex px-2 py-1 rounded-full text-[10px] font-bold bg-slate-500/10 text-slate-500 border border-slate-500/20">Closed / Done</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 text-right">
                                        <a href="../campaign-details.php?id=<?php echo $camp['id']; ?>" class="inline-flex items-center justify-center bg-indigo-50 dark:bg-indigo-950/40 hover:bg-indigo-100 dark:hover:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 text-xs font-semibold py-1.5 px-3.5 rounded-lg transition">Manage</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-8 text-slate-400">
                    <i class="fa-regular fa-folder-open text-3xl mb-2 block"></i>
                    <p class="text-sm">You haven't posted any marketing campaigns yet.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Active Deal Contracts Table -->
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/60 rounded-2xl shadow-xl p-6">
            <h3 class="text-lg font-bold text-slate-800 dark:text-white border-b border-slate-100 dark:border-slate-700/60 pb-3 mb-6">Running Creator / Promoter Partnerships</h3>
            
            <?php if (count($active_deals) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-700 text-slate-400 font-semibold">
                                <th class="pb-3">Sponsorship Deal</th>
                                <th class="pb-3">Hired Talent</th>
                                <th class="pb-3">Role</th>
                                <th class="pb-3">Clicks / Conv.</th>
                                <th class="pb-3">Price / Rate</th>
                                <th class="pb-3">Milestone</th>
                                <th class="pb-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                            <?php foreach ($active_deals as $deal): ?>
                                <tr class="text-slate-700 dark:text-slate-200 hover:bg-slate-50/50 dark:hover:bg-slate-700/10 transition">
                                    <td class="py-4 font-semibold"><?php echo sanitize($deal['campaign_title']); ?></td>
                                    <td class="py-4">
                                        <div class="font-bold text-slate-900 dark:text-white"><?php echo sanitize($deal['talent_name']); ?></div>
                                        <div class="text-xs text-slate-400">@<?php echo sanitize($deal['username']); ?></div>
                                    </td>
                                    <td class="py-4">
                                        <?php if ($deal['talent_role'] === 'influencer'): ?>
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-500/10 text-indigo-500 border border-indigo-500/20 uppercase tracking-wide">Influencer</span>
                                        <?php else: ?>
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold bg-violet-500/10 text-violet-500 border border-violet-500/20 uppercase tracking-wide">Promoter</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4">
                                        <?php if ($deal['talent_role'] === 'promoter'): ?>
                                            <span class="font-mono font-bold"><?php echo intval($deal['clicks']); ?> clicks</span> /
                                            <span class="font-mono font-bold text-emerald-500"><?php echo intval($deal['conversions']); ?> conv.</span>
                                        <?php else: ?>
                                            <span class="text-slate-400 italic text-xs">Content Post</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 font-bold text-emerald-500">$<?php echo number_format($deal['agreed_price'], 2); ?></td>
                                    <td class="py-4">
                                        <?php if ($deal['status'] === 'pending'): ?>
                                            <span class="inline-flex px-2 py-1 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-500 border border-amber-500/20">Awaiting Activation</span>
                                        <?php else: ?>
                                            <span class="inline-flex px-2 py-1 rounded-full text-[10px] font-bold bg-indigo-500/10 text-indigo-500 border border-indigo-500/20">Active Partnership</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 text-right">
                                        <a href="../deals.php" class="inline-flex items-center justify-center bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-xs font-semibold py-1.5 px-3 rounded-lg transition">Manage</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-8 text-slate-400">
                    <i class="fa-regular fa-handshake text-3xl mb-2 block"></i>
                    <p class="text-sm">You have no active creator sponsorships at the moment.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/header.php'; ?>
