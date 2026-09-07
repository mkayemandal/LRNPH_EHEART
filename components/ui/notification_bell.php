<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Notification bell</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">

<div class="flex justify-end p-6">
  <div class="relative" id="notif-wrapper">

    <!-- Bell button -->
    <button id="notif-bell-btn" class="relative p-2 rounded-full hover:bg-gray-100">
      <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
        <path stroke-linecap="round" stroke-linejoin="round" d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
      </svg>
      <span id="notif-badge" class="absolute -top-1 -right-1 bg-red-500 text-white text-[11px] font-medium rounded-full min-w-[16px] text-center px-1 leading-tight">73</span>
    </button>

    <!-- Preview box -->
    <div id="notif-box" class="hidden absolute right-0 top-11 w-[300px] bg-white border border-gray-200 rounded-xl shadow-lg overflow-hidden z-50">

      <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
        <span class="text-sm font-medium text-gray-900">Notifications</span>
        <span class="bg-red-500 text-white text-[11px] font-medium rounded-full px-1.5 py-0.5">73</span>
      </div>

      <div id="notif-list" class="max-h-64 overflow-y-auto">
        <!-- items injected by JS -->
      </div>

      <button id="notif-see-all" class="w-full text-sm font-medium text-gray-900 border-t border-gray-100 py-2.5 hover:bg-gray-50">
        See all
      </button>
    </div>

  </div>
</div>

<script>
  // sample data, swap for real API data
  const notifications = [
    {
      title: "PMC materials",
      subtitle: "Review material breakdown updates",
      count: 37,
      iconBg: "bg-red-50",
      iconColor: "text-red-600",
      iconPath: "M21 8l-9-5-9 5 9 5 9-5z M3 8v8l9 5 9-5V8 M12 13v8",
    },
    {
      title: "Create PMC plan",
      subtitle: "Plan uploaded POs without module 4",
      count: 34,
      iconBg: "bg-purple-50",
      iconColor: "text-purple-600",
      iconPath: "M8 7V3m8 4V3M4 11h16M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z",
    },
    {
      title: "Suggested TO items",
      subtitle: "Items ready for a TO request",
      count: 2,
      iconBg: "bg-red-50",
      iconColor: "text-red-600",
      iconPath: "M9 18h6 M10 21h4 M12 3a6 6 0 0 0-4 10.5c.4.4.6 1 .6 1.5h6.8c0-.5.2-1.1.6-1.5A6 6 0 0 0 12 3z",
    },
  ];

  const listEl = document.getElementById("notif-list");

  function renderNotifications() {
    listEl.innerHTML = notifications
      .slice(0, 3)
      .map(
        (n) => `
        <div class="flex gap-2.5 px-4 py-2.5 border-b border-gray-50 last:border-b-0 hover:bg-gray-50 cursor-pointer">
          <div class="w-8 h-8 rounded-full ${n.iconBg} ${n.iconColor} flex items-center justify-center shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
              <path stroke-linecap="round" stroke-linejoin="round" d="${n.iconPath}" />
            </svg>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-[13px] font-medium text-gray-900 m-0">${n.title}</p>
            <p class="text-xs text-gray-500 m-0 truncate">${n.subtitle}</p>
          </div>
          <span class="bg-red-500 text-white text-[11px] font-medium rounded-full px-1.5 h-fit py-0.5 self-center">${n.count}</span>
        </div>
      `
      )
      .join("");
  }

  renderNotifications();

  const wrapper = document.getElementById("notif-wrapper");
  const bellBtn = document.getElementById("notif-bell-btn");
  const box = document.getElementById("notif-box");
  const seeAllBtn = document.getElementById("notif-see-all");

  bellBtn.addEventListener("click", () => {
    box.classList.toggle("hidden");
  });

  // close box on outside click
  document.addEventListener("click", (e) => {
    if (!wrapper.contains(e.target)) {
      box.classList.add("hidden");
    }
  });

  // go to main notification page
  seeAllBtn.addEventListener("click", () => {
    window.location.href = "/notifications.html"; // swap for your real page
  });
</script>

</body>
</html>