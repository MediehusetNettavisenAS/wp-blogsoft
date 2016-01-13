jQuery(document).ready(function ($) {
    var scrollBArray = [ "scrollbars=no", /* rel="0" */
        "scrollbars=yes" /* rel="1" */
    ];
    $('.newWindow').click(function (event) {
        var url = $(this).attr("href");
        var w1 = $(this).attr("data-width"), h1 = $(this).attr("data-height");
        var left = ($(window).width() / 2) - (w1 / 2),
            top = ($(window).height() / 2) - (h1 / 2);
        var windowName = $(this).attr("id");
        var scrollB = scrollBArray[$(this).attr("rel")];
        window.open(url, windowName, "width=" + w1 + ", height=" + h1 + ", top=" + top + ", left=" + left + ", " + scrollB);
        event.preventDefault();
    });


    $(document).on('click', '#addBlogsoftRule', function(){
        var selectedWPCategory = $("#cat").val();
        var selectedBSCategory = $("#blogsoftCategories").val();

        if(selectedWPCategory == 0) {
            alert('Please select a Wordpress category name');
            return;
        }

        if(selectedBSCategory == 0) {
            alert('Please select a Blogsoft category name');
            return;
        }

        var alreadyExist = false;
        $(".wp_cat").each(function() {
            if($(this).val() == selectedWPCategory) {
                alreadyExist = true;
                return false;
            }
        });

        if(!alreadyExist) {
            $("#blogsoftRules").append(
                '<li class="list-group-item">' +
                    '<input type="hidden" value="'+ selectedWPCategory +'" name="wp_cat['+ ruleIndex +']" class="wp_cat" />' +
                    '<input type="hidden" value="'+ selectedBSCategory +'" name="bs_cat['+ ruleIndex +']" class="bs_cat" />' +
                    '<span style="float:right;"><a href="#">Remove</a></span>'+
                    $("#cat option:selected").text().trim() + ' -->' + $("#blogsoftCategories option:selected").text().trim() +
                '</li>');
            ruleIndex++;
        } else {
            alert( $("#cat option:selected").text() + ' is already added');
        }
    });

    $(document).on('click', '#blogsoftRules li a', function(){
        $(this).closest('li').remove();
        return false;
    });

    if($("#publish_in_blogsoft").is(':checked')) {
        $(".blogsoft_active").show();  // checked
    }

    $('#publish_in_blogsoft').change(function () {
        if ($(this).is(':checked')) {
            $(".blogsoft_active").show();
        } else {
            $(".blogsoft_active").hide();
        }
    });


    $('#frmBlogsoftSettings').submit(function(e) {
        if($("#publish_in_blogsoft").is(':checked')) {
            /*if(!$('.wp_cat').length) {
               alert('Please select atleast one category rule');
               return false;
            }*/
        }
        return true;
    });

});