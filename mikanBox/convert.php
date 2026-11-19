<?php
// The data tool lives in the admin screen (admin.php?view=import, views/data-tool.php). This file
// stays so that old bookmarks, and updates from versions that had the converter here, lead there.
header('Location: admin.php?view=import', true, 303);
exit;
