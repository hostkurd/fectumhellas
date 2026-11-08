<?php
declare(strict_types=1);

namespace App\Admin\Widgets;

use FloCMS\Admin\Widgets\ActivityWidget;

/** The panel's latest changes, in its place on the dashboard; Administrators only (activity.view, Q5). */
final class RecentActivity extends ActivityWidget
{
    protected int $sort = 80;
}
