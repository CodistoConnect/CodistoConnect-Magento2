require([
    "jquery"
], function ($) { // @codingStandardsIgnoreLine Squiz.Functions.MultiLineFunctionDeclaration.SpaceBeforeOpenParen

    $("#create-account-modal").on("click", ".option", function (e) { // @codingStandardsIgnoreLine Squiz.Functions.MultiLineFunctionDeclaration.SpaceBeforeOpenParen

        $("#create-account-modal .option").removeClass("active");
        $(this).addClass("active").find("INPUT[type=radio]").attr("checked", "checked");

    });

    $("#create-account-modal .selection").css({
        opacity : 0.1
    });

    $.ajax({
        type: "GET",
        url: "https://ui.codisto.com/getcountrylist",
        dataType : "jsonp",
        success: function(o){
            $(".select-html-wrapper").html(o);
        },
        complete: function(){
            $("#create-account-modal .selection").css({
                opacity : 1
            });
        }
    });

});
