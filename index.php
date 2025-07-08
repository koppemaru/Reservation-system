<?php
// index.php
include 'db.php';
$rooms = $db->query("SELECT id, name FROM rooms ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>予約システム</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet" />
  <style>
    :root {
      --font-family-sans-jp: 'Noto Sans JP', 'Inter', sans-serif;
      --bg-color: #f8f9fa;
      --sidebar-bg: #ffffff;
      --border-color: #e9ecef;
      --text-color: #212529;
      --accent-color: #007bff;
      --accent-hover-bg: #f1f3f5;
    }
    html, body {
      height: 100%; margin: 0; font-family: var(--font-family-sans-jp);
      color: var(--text-color); background-color: var(--bg-color); font-size: 16px;
      overflow: hidden;
    }
    #container { display: flex; height: 100vh; }
    .mobile-header {
      display: none; position: fixed; top: 0; left: 0; right: 0;
      height: 60px; background-color: var(--sidebar-bg);
      border-bottom: 1px solid var(--border-color); z-index: 1010;
      align-items: center; padding: 0 1rem;
    }
    #menu-toggle { background: none; border: none; font-size: 1.8rem; cursor: pointer; color: var(--text-color); }
    #sidebar {
      width: 280px; min-width: 280px; background-color: var(--sidebar-bg);
      border-right: 1px solid var(--border-color); overflow-y: auto;
      padding: 1rem 0; display: flex; flex-direction: column; transition: transform 0.3s ease;
    }
    .sidebar-header {
        padding: 0 1.25rem 0.5rem 1.25rem; font-size: 1.2rem; font-weight: 700;
        border-bottom: 1px solid var(--border-color); margin-bottom: 0.5rem;
        display: flex; justify-content: space-between; align-items: center;
    }
    .sidebar-close { display: none; background: none; border: none; font-size: 1.8rem; cursor: pointer; }
    .nav-item {
      display: flex; align-items: center; justify-content: space-between;
      padding: 0.8rem 1.25rem; cursor: pointer; user-select: none;
      transition: all 0.15s ease; border-left: 4px solid transparent; font-weight: 500;
      color: inherit;
      text-decoration: none;
    }
    .nav-item:hover {
      background-color: var(--accent-hover-bg);
      color: inherit;
      text-decoration: none;
    }
    .nav-item.active {
      background-color: var(--accent-hover-bg); border-left-color: var(--accent-color); color: var(--accent-color);
    }
    .nav-item .material-symbols-outlined { font-size: 1.25rem; transition: transform 0.2s ease-in-out; }
    .nav-item.expanded .room-icon { transform: rotate(90deg); }
    .device-list { list-style: none; padding: 0; margin: 0; background-color: #fafafa; }
    .device-item {
      padding: 0.6rem 1.25rem 0.6rem 2.5rem; cursor: pointer;
      font-size: 0.95em; transition: background-color 0.15s ease;
    }
    .device-item:hover { background-color: #f0f0f0; }
    .device-item.active { background-color: #e9ecef; font-weight: 700; }
    #content-area { flex: 1; position: relative; }
    #content-frame { width: 100%; height: 100%; border: none; }
    #loader {
      position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
      width: 50px; height: 50px; border: 5px solid var(--accent-hover-bg);
      border-top-color: var(--accent-color); border-radius: 50%;
      animation: spin 1s linear infinite; display: none; z-index: 10;
    }
    @keyframes spin { to { transform: translate(-50%, -50%) rotate(360deg); } }
    @media (max-width: 768px) {
      body { overflow: hidden; }
      .mobile-header { display: flex; }
      #sidebar {
        position: fixed; top: 0; bottom: 0; left: 0; z-index: 1020;
        transform: translateX(-100%); box-shadow: 0 0 15px rgba(0,0,0,0.1);
      }
      #sidebar.visible { transform: translateX(0); }
      .sidebar-close { display: block; }
      #content-area { height: calc(100vh - 60px); margin-top: 60px; }
      #sidebar-overlay {
        position: fixed; top: 0; left: 0; right: 0; bottom: 0;
        background-color: rgba(0,0,0,0.5); z-index: 1015; display: none;
        opacity: 0; transition: opacity 0.3s ease;
      }
      #sidebar-overlay.visible { display: block; opacity: 1; }
    }
  </style>
</head>
<body>
  <header class="mobile-header">
    <button id="menu-toggle" class="material-symbols-outlined">menu</button>
  </header>
  <div id="container">
    <div id="sidebar">
        <div class="sidebar-header">
            <span>予約システム</span>
            <button class="sidebar-close material-symbols-outlined">close</button>
        </div>
        <div id="room-list">
            <div class="nav-item" data-target="all-reservations">
                <span><span class="material-symbols-outlined" style="vertical-align: bottom; margin-right: 0.5rem;">grid_view</span>全予約表示</span>
            </div>
            <hr style="margin: 0.5rem 0;">
            <?php foreach($rooms as $r): ?>
              <div class="nav-item room-item" data-room-id="<?= $r['id'] ?>">
                <span><?= htmlspecialchars($r['name'], ENT_QUOTES) ?></span>
                <span class="material-symbols-outlined room-icon">chevron_right</span>
              </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div id="sidebar-overlay"></div>
    <div id="content-area">
        <div id="loader"></div>
        <iframe id="content-frame" name="content-frame" src=""></iframe>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
  <script>
  jQuery(function($) {
    const $sidebar = $('#sidebar');
    const $overlay = $('#sidebar-overlay');
    const $roomList = $('#room-list');
    const $contentFrame = $('#content-frame');
    const $loader = $('#loader');

    function showLoader() { $loader.show(); }
    function hideLoader() { $loader.hide(); }
    
    function toggleSidebar() {
      $sidebar.toggleClass('visible');
      $overlay.toggleClass('visible');
    }
    $('#menu-toggle, .sidebar-close, #sidebar-overlay').on('click', toggleSidebar);

    function activateNavItem($el) {
        $('.nav-item, .device-item').removeClass('active');
        $el.addClass('active');
    }

    function loadIntoFrame(url) {
        showLoader();
        $contentFrame.attr('src', url);
        if (window.innerWidth <= 768) {
            closeSidebar();
        }
    }

    $roomList.on('click', '.nav-item[data-target="all-reservations"]', function() {
        $roomList.find('.device-list').slideUp('fast', function() { $(this).remove(); });
        $roomList.find('.room-item').removeClass('active expanded');
        activateNavItem($(this));
        loadIntoFrame('all_reservations.php');
    });

    $roomList.on('click', '.room-item', function(e) {
      e.preventDefault();
      const $room = $(this);
      if ($room.hasClass('expanded')) {
          $room.next('.device-list').slideUp('fast', function() { $(this).remove(); });
          $room.removeClass('expanded');
          return;
      }
      $('.device-list').slideUp('fast', function() { $(this).remove(); });
      $('.room-item').removeClass('expanded');
      $room.addClass('expanded');
      activateNavItem($room);
      showLoader();
      $.get('get_devices.php', { room_id: $room.data('room-id') })
        .done(function(html) {
          const $deviceList = $('<div class="device-list" style="display:none;"></div>').html(html);
          $room.after($deviceList);
          $deviceList.slideDown('fast');
        })
        .fail(function() {
            alert('装置リストの取得に失敗しました');
        })
        .always(function() {
            hideLoader();
        });
    });

    $roomList.on('click', '.device-item', function(e) {
        e.preventDefault();
        const $device = $(this);
        if ($device.hasClass('active')) return;
        $('.device-item').removeClass('active');
        $device.addClass('active');
        loadIntoFrame(`calendar_month.php?equipment_id=${$device.data('equipment-id')}`);
    });
    
    $contentFrame.on('load', function() {
        hideLoader();
    });

    $roomList.find('.nav-item[data-target="all-reservations"]').trigger('click');
  });
  </script>
</body>
</html>
