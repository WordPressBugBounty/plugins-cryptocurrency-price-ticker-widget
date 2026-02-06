jQuery(function ($) {
  // Collect all plugin prefixes
  const prefixes = [];
  for (let key in window) {
    if (key.includes("BFNotice")) {
      const p = key.replace("BFNotice", "");
      if (window[`${p}BFNotice`] && window[`${p}ReviewObj`]) {
        prefixes.push(p);
      }
    }
  }

  if (!prefixes.length) return;

  // Process each plugin's notice
  prefixes.forEach((prefix, index) => {
    const noticeData = window[`${prefix}BFNotice`];
    const Data = window[`${prefix}ReviewObj`];

    if (noticeData.dismissed) return;

    function detectTarget() {
      const { menu_slug: menuSlug } = Data;

      // Try regular admin page menu
      let mainMenu = $(`#toplevel_page_${menuSlug}`);
      
      // If not found, try custom post type menu pattern
      if (!mainMenu.length) {
        mainMenu = $(`#menu-posts-${menuSlug}`);
      }
      
      return mainMenu.length ? mainMenu : $("body");
    }

    function updateAnchorPosition(anchor, target, verticalOffset) {
      const targetOffset = target.offset();
      anchor.css({
        top: targetOffset.top + (target.outerHeight() / 2) + verticalOffset,
        left: targetOffset.left + target.outerWidth()
      });
    }

    function showPointer(currentPrefix, currentData, currentNoticeData) {
      const originalTarget = detectTarget();
      if (!originalTarget.length) return;

      // Check if pointer already exists for this plugin
      if ($(`#${currentPrefix}-pointer-notice`).length) return;

      const verticalOffset = index * 230; // Spacing between notices

      // Create or get unique anchor element for this plugin's pointer
      let anchor = $(`#${currentPrefix}-pointer-anchor`);
      if (!anchor.length) {
        anchor = $('<span>', {
          id: `${currentPrefix}-pointer-anchor`,
          class: 'review-notice-anchor',
          css: {
            position: 'absolute',
            width: '1px',
            height: '1px',
            opacity: 0,
            pointerEvents: 'none',
            zIndex: 10000
          }
        }).appendTo('body');
        
        // Set initial position
        updateAnchorPosition(anchor, originalTarget, verticalOffset);
      } else {
        // Update position if anchor already exists
        updateAnchorPosition(anchor, originalTarget, verticalOffset);
      }

      // Create and open pointer
      anchor.pointer({
        content: currentNoticeData.content,
        position: { edge: "left", align: "center" }
      }).pointer("open");
      
      // Get the newly created pointer element (the one without any prefix class yet)
      const newPointer = $(".wp-pointer").filter(function() {
        return !$(this).attr("data-plugin-prefix");
      }).first()
        .addClass(`${currentPrefix}-pointer-identified`)
        .attr({
          "id": `${currentPrefix}-pointer-notice`,
          "data-plugin-prefix": currentPrefix
        });

      // Add prefix-specific classes and setup dismiss handler
      newPointer.find(".wp-pointer-content").addClass(`${currentPrefix}-pointer-content`);
      
      const dismissButton = newPointer.find(".wp-pointer-buttons .close").addClass(`${currentPrefix}-dismiss`);
      dismissButton.find(".close").off("click");
      dismissButton.on("click", function () {
        $.post(currentData.ajax_url, {
          action: currentData.action,
          nonce: currentData.nonce
        });

        newPointer.fadeOut(300, function() {
          $(this).remove();
          anchor.remove();
        });
      });
    }

    // Trigger pointer after load
    setTimeout(() => {
      showPointer(prefix, Data, noticeData);
    }, 800 + (index * 300));

    $(document).on(`click.${prefix}`, "#adminmenu a", function () {
      setTimeout(() => {
        showPointer(prefix, Data, noticeData);
      }, 500 + (index * 300));
    });

    // Listen for admin menu collapse/expand
    $(document).on(`click.${prefix}`, "#collapse-button", function () {
      setTimeout(() => {
        const anchor = $(`#${prefix}-pointer-anchor`);
        const pointer = $(`#${prefix}-pointer-notice`);
        
        if (anchor.length && pointer.length) {
          const target = detectTarget();
          if (target.length) {
            updateAnchorPosition(anchor, target, index * 230);
            pointer.position({
              my: "left center",
              at: "right center",
              of: anchor
            });
          }
        }
      }, 350); 
    });
  });
});
