$(document).ready(function() {
    $('.loader').hide();
});

//------------------DROPDOWN OPTIONS---------------------
var ds1selector = '.dropdown#dataseries1 ul li';
var yearselector = '.dropdown#year ul li';
var areatypeselector = '.dropdown#areatype ul li';
var areaselector = '.dropdown#area ul li';

$(document).on('click', '.dropdown ul li', function(event) { //make selected option the active element
    var target = $(event.target);

    //Set elements in dropdown to active if clicked
    if(target.parents('#dataseries1').length) {
    	$('#dataseries1button').text($(this).text());
    	$(ds1selector).removeClass('active');
   		$(this).addClass('active');
    }

    if(target.parents('#year').length) {
        $('#yearbutton').text($(this).text());
        $(yearselector).removeClass('active');
        $(this).addClass('active');
    }

    if(target.parents('#areatype').length) {
    	$('#areatypebutton').text($(this).text());
    	$(areatypeselector).removeClass('active');
   		$(this).addClass('active');

        //Reset Area Button
        $('#areabutton').text('Area');

        //Fix area list
        $('#arealist li').remove();
        
        $.get("functions.php", 
            { 'check': 'areatype',
                'var1': $(this).text(),
            },
            function(data) { 
                $('#arealist').append(data); 
            }, 
            "text"
        );
    }

    if(target.parents('#area').length) {
    	$('#areabutton').text($(this).text());
    	$(areaselector).removeClass('active');
   		$(this).addClass('active');
    }
});

//-------------------ADD BUTTON----------------------
var count = 0;
var temp;

//Function used to add the data to the table
function adddata(ds1, year, areatype, area, temp) {
    if(!$('table').html() || ($('table').html() == '<tr></tr>')) { //Add columns if there are none
            var col = "<tr><th><button class = \"compare\">Compare Series</button></th>";
            col += "<th>Remove</th>";
            col += "<th>Correlation</th>";
            col += "<th>Area 1</th>";
            col += "<th>Area 2</th>";
            col += "<th>" + ds1 + "</th></tr>";
            count += 1;
            $('table').append(col);
        }
    else {
        if($('th').text().search(ds1) == -1) {
            //add new column name
            $('table').find('tr:first-child th:last-child').after('<th>' + ds1 + '</th>');

            //add the new column
            $('table').find('tr').each(function() {
                $(this).find('td:last-child').after('<td></td>');
            });

            count += 1;
        }
    }

    //Add empty rows
    var markup = '<tr><td><input type="checkbox" id="comparecheck"></td>';
    markup += "<td><button class = \"remove\">Remove Series</button></td>";
    markup += "<td>N/A</td>"; //Correlation
    markup += "<td>" + area + " " + year + "</td>"; //Area 1
    markup += "<td>N/A</td>"; //Area 2
    for(i = 0; i < count; i++) {
        markup += "<td></td>";
    }
    $('table').append(markup);

    //Put data in the table
    var colcount = 1;
    $('table').find('th').each(function() {
        if($(this).text() == ds1) {
            $('table').find('tr:last-child').each(function() {
                $(this).find('td:nth-child(' + colcount +')').text(temp);
            });
        }
        colcount += 1;            
    }); 
    $('#loader').hide();
}

//Add data when the button is clicked
$(document).on('click', '.add', function() {
    var ds1 = $('#dataseries1button').text();
    var year = $('#yearbutton').text();
    var areatype = $('#areatypebutton').text();
    var area = $('#areabutton').text();

    //Alert if fields need to be completed
    if(ds1.search('Data Series') != -1 || 
        year.search('Year') != -1 ||
        areatype.search('States') != -1 || 
        area.search('Area') != -1) {
            alert('Cannot add series - fill in all menus');
    }
    else if(ds1.search('Fastest Growing Industry') != -1 &&
        year.search('2015') != -1) {
        alert('Cannot check for fastest growing industry for this year. Select different year.');
    }
    else {
        //Get query results for data series 1

        $('.loader').show();
        var load = setTimeout(function() {
            temp = "Try Again";
        }, 60000);

        $.get("functions.php", 
            { 'check': 'add',
                'var1': ds1,
                'var2': areatype,
                'var3': area,
                'var4': year
            },
            function(data) {
                temp = data; 
                clearTimeout(load);
                adddata(ds1, year, areatype, area, temp);
                $('.loader').hide();
            }, 
            "text"
        );
    }
});

//-------------------COMPARE BUTTON----------------
var checkbox = 2;
$(document).on('change', '#comparecheck', function() {
   if($('#comparecheck:checked').length > checkbox) {
       $(this).prop('checked', false);
       alert("You can only compare 2 data series!");
   }
});

function comparedata(area1, first, area2, second, ds1, ds2, temp) {
    var markup = '<tr><td></td>';
    markup += "<td><button class = \"remove\">Remove Series</button></td>";
    markup += "<td>" + temp + "</td>"; //Correlation
    markup += "<td>" + area1 + "</td>"; //Area 1
    markup += "<td>" + area2 + "</td>"; //Area 2

    for(i = 0; i < count; i++) { //Fill table
        markup += "<td></td>";
    }
    $('table').append(markup);

    var colcount = 1;
    $('table').find('th').each(function() {
        if($(this).text() == ds1) { //CHANGE TO CHECK REAL HEADER NAME
            $('table').find('tr:last-child').each(function() {
                $(this).find('td:nth-child(' + colcount +')').text(first);
            });
        }
        else if($(this).text() == ds2) { //CHANGE TO CHECK REAL HEADER NAME
            $('table').find('tr:last-child').each(function() {
                $(this).find('td:nth-child(' + colcount +')').text(second);
            });
        }
        colcount += 1;            
    }); 
}

$(document).on('click', '.compare', function() {
    if($('#comparecheck:checked').length < checkbox) {
        alert("Select 2 data series to compare!");
    }
    else {
        var checked = 0;
        var secondchecked = 0;
        var thirdchecked = 0;
        var col = 0;
        var area1, area2, first, second, ds1, ds2;

        $('table').find($('#comparecheck:checked').parents('tr').children('td')).each(function() {
            var t = $(this).text();
            if(t) {
                if((t != 'Remove Series') && (t != 'N/A')) {
                    if(checked == 0 || checked == 2) {
                        if(secondchecked == 0) {
                            area1 = t; //First area to compare
                        }   
                        else if(secondchecked == 1) {
                            area2 = t; //Second area to compare
                        }
                        secondchecked += 1;  
                    }
                    if(checked == 1 || checked == 3) {
                        if(thirdchecked == 0) {
                            first = t; //First data series to compare
                            col = $(this).index() + 1;
                            ds1 = $('table th:nth-child(' + col + ')').text();
                        }   
                        else if(thirdchecked == 1) {
                            second = t; //Second data series to compare
                            col = $(this).index() + 1;
                            ds2 = $('table th:nth-child(' + col + ')').text();
                        }
                        thirdchecked += 1;
                    }
                    checked += 1;
                }
            }
        });

        if(ds1 == "Fastest Growing Industry" || 
            ds1 == "Most Spoken Foreign Language" ||
            (ds1 == "Average Age" && area1 == "Florida") ||
            (ds1 == "Percentage of Migrants" && area1 == "Florida")) {
            alert("Cannot use data series '" + ds1 + "' to correlate. Pick a different series!");
        }
        else if(ds2 == "Fastest Growing Industry" || 
            ds2 == "Most Spoken Foreign Language" ||
            (ds2 == "Average Age" && area1 == "Florida") ||
            (ds2 == "Percentage of Migrants" && area1 == "Florida")) {
            alert("Cannot use data series '" + ds2 + "' to correlate. Pick a different series!");
        }
        else {
            $('.loader').show();
            var load = setTimeout(function() {
                temp = "Try Again";
            }, 60000);

            $.get("functions.php", 
                { 'check': 'compare',
                    'var1': area1,
                    'var2': area2,
                    'var3': ds1,
                    'var4': ds2
                },
                function(data) { 
                    temp = data;
                    clearTimeout(load);
                    comparedata(area1, first, area2, second, ds1, ds2,temp); 
                    $('.loader').hide();
                }, 
                "text"
            );  
        }   
    }
});

//-------------------REMOVE BUTTON----------------
$(document).on('click', '.remove', function() {
    $(this).parents('tr').remove();

    var colcount = 1;
    $('table').find('th').each(function() {
        var rowcount = 0;
        $("table tr td:nth-child(" + colcount + ")").each(function () {
            if($(this).html()) {
                rowcount += 1;
            }
        });
        if(rowcount == 0) {
            $(this).remove();
        }
    }); 

    if($('table').html() == '<tr></tr>') {
        count = 0;
    }          
    
});

$(document).on('click', '.tuplecount', function() {
   $('.loader').show();

   $.get("functions.php", 
        { 
            'check': 'tuplecount',  
        },
        function(data) { 
            temp = data;
            $('.loader').hide();
        }, 
        "text"
    );   

    $('.tuplecount').text(temp);   
});


