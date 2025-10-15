<?php
// includes/user-menu.php
defined('INCLUDED') or die('لا يمكن الوصول مباشرة');
?>
<div class="user-menu">
    <div class="user-info">
        <div class="user-name"><?php echo $_SESSION['user_id']; ?></div>
        <div class="user-role">موظف مبيعات</div>
    </div>
    <img src="../images/user.png" alt="User">
</div>