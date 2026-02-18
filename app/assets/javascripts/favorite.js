(function($) {
  Favorite = {
    _escape_html: function(value) {
      return String(value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");
    },

    _safe_user_id: function(value) {
      var id = String(value || "").replace(/[^0-9]/g, "");
      return id === "" ? "0" : id;
    },

    _user_link: function(user) {
      var id = Favorite._safe_user_id(user.id);
      var name = Favorite._escape_html(user.name || "");
      return '<a href="/user/show/' + id + '">' + name + '</a>';
    },

    link_to_users: function(users) {
      var html = ""
      users = users || []

      if (users.length === 0) {
        return "no one"
      } else {
        html = users.slice(0, 6).map(Favorite._user_link).join(", ")

        if (users.length > 6) {
          html += '<span id="remaining-favs" style="display: none;">' + users.slice(6).map(Favorite._user_link).join(", ") + '</span> <span id="remaining-favs-link">(<a href="#" onclick="$(\'remaining-favs\').show(); $(\'remaining-favs-link\').hide(); return false;">' + (users.length - 6) + ' more</a>)</span>'
        }

        return html
      }
    }
  }
}) (jQuery);
