<?php
    $conn = oci_connect($username = 'cgfussel', $password = 'Economy321', $connection_string = '//oracle.cise.ufl.edu/orcl');

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    } 

    //Get needed variables from Javascript
    $check = $_GET['check'];
    $var1 = $_GET['var1'];
    $var2 = $_GET['var2'];
    $var3 = $_GET['var3'];
    $var4 = $_GET['var4'];

    //Results stored in $data
    $data = '';
    $array1 = array();
    $array2 = array();

    //Queries stored in $q
    $q = '';

    //Count number of tuples in database
    if($check == 'tuplecount') {
        $q = "select
            (select count(household.serialno) from household)+
            (select count(communities.communityid) from communities)+
            (select count(income.iserialno) from income)+
            (select count(industry.industryid) from industry)+
            (select count(person.serialno) from person)+
            (select count(primarylanguage.languageid) from primarylanguage)+
            (select count(states.stateid) from states) as total from COMMUNITIES, Household, income, industry, person, primarylanguage, states where rownum=1";
        $stid = oci_parse($conn, $q);
        oci_define_by_name($stid, 'TOTAL', $name);
        oci_execute($stid);
        
        while(oci_fetch($stid)) {
            $data = $name;
        }

        oci_free_statement($stid);
        echo $data;
    }
    else if($check == 'areatype') { //Fill in 'Area' dropdown based on Area Type
        //$var1 is the name of the state we are using
        $q = "SELECT distinct communities.name 
                FROM Communities, States 
                WHERE communities.belongsTo = states.stateid 
                    AND states.name = '" . $var1 . "' ORDER BY communities.name";
        $stid = oci_parse($conn, $q); //. $var1);
        oci_define_by_name($stid, 'NAME', $name);
        oci_execute($stid);

        $data = '<li role="presentation"><a role="menuitem" tabindex="-1" href="#"><b>' . $var1 . '</b></a></li>';
        while(oci_fetch($stid)) {
            $data = $data . '<li role="presentation"><a role="menuitem" tabindex="-1" href="#">' . $name . '</a></li>';
        }
        echo $data; 
    } //Add Data when "Add Series" is selected
    else if($check == 'add') { 
        //$var1 is the data series
        //$var2 is area type (state or community)
        //$var3 is the specific state or community 
        //$var4 is the year we're looking at
        
        $data = performQuery($var1, $var3, $var4, $conn);
        echo $data;
    }
    else if($check == 'compare') {
        //$var1 is the first area to compare
        //$var2 is the second area to compare
        //$var3 is the first data series to compare
        //$var4 is the second data series to compare

        //Get rid of year from the end of the string
        $var1 = substr($var1, 0, -5);
        $var2 = substr($var2, 0, -5);

        //Getting values from first data series
        $year = 2012;
        for($i = 0; $i <= 3; $i++) {
            $array1[$i] = performQueryCompare($var3, $var1, $year, $conn, $i, $array1);
            $year = $year + 1;
        }

        //Getting values from second data series
        $year = 2012;
        for($i = 0; $i <= 3; $i++) {
            $array2[$i] = performQueryCompare($var4, $var2, $year, $conn, $i, $array2);
            $year = $year + 1;
        }

        //Do correlation
        $data = Correlation($array1, $array2);
        echo $data;
    }

//-----------------------PERFORM QUERIES-------------------------
    function performQuery($var1, $var3, $var4, $conn) {
        if($var1 == 'Median Age') {
            $q = "";
            if($var3 == 'Florida') {
                $q = "select states.name, Median(person.Agep)
                        from person join household on (person.serialNo = household.serialNo and  person.year = household.year)
                        join communities on (PUMA = communityID and communities.year = household.year) join states on 
                        (communities.BELONGSTO = states.stateID and communities.year = states.year)
                        where states.name = 'Florida' and person.year = " . $var4 . " group by states.name";
            }
            else {
                 $q = "select Median(person.Agep)
                    from person join household on (person.serialNo = household.serialNo and  person.year = household.year)
                    join communities on (PUMA = communityID and communities.year = household.year)
                    where communities.name = '" . $var3 . "' and person.year = " . $var4 . " group by communities.name";
            }
           
            $stid = oci_parse($conn, $q);
            oci_define_by_name($stid, 'MEDIAN(PERSON.AGEP)', $name);
            oci_execute($stid);
        }
        else if($var1 == 'Fertility Rate') {
            $q = "";
            if($var3 == 'Florida') {
                $q = "select ROUND(sum(household.NOC)/(Count(*) / 2),2)
                        from person join household on (person.serialNo = household.serialNo and person.year = household.year)
                        join communities on (PUMA = communityID and communities.year = household.year)
                        join states on (communities.BELONGSTO = states.stateID and communities.year = states.year)
                        where states.name = 'Florida' and household.year = " . $var4 . " group by states.name";
            }
            else {
                 $q = "select ROUND(sum(household.NOC)/(Count(*) / 2),2)
                    from person join household on (person.serialNo = household.serialNo and person.year = household.year)
                    join communities on (PUMA = communityID and communities.year = household.year)
                    where communities.name = '" . $var3 . "' and household.year = '" . $var4 . "' group by communities.name";
            }
            $stid = oci_parse($conn, $q);
            oci_define_by_name($stid, 'ROUND(SUM(HOUSEHOLD.NOC)/(COUNT(*)/2),2)', $name);
            oci_execute($stid);
        }
        else if($var1 == 'Median Income') {
            $q = "";
            if($var3 == 'Florida') {
                $q = "with T AS (select * from income, household, communities
                        where income.iserialno=household.serialno
                        AND communities.communityid=household.puma
                        AND communities.year = income.year
                        AND household.year= income.year
                        AND communities.year = " . $var4 . "
                        )

                        select median(pincome) from 
                        (select (retirement+interest+wages+assist+ss+disability+sinc) as pincome, iserialno 
                        from (select sum((coalesce(retp,0))*adjinc*power(10,-6)) retirement, 
                        sum((coalesce(intp,0))*adjinc*power(10,-6)) interest, 
                        sum((coalesce(WAGP,0))*adjinc*power(10,-6)) wages, 
                        sum((coalesce(pap,0))*adjinc*power(10,-6)) assist, 
                        sum((coalesce(ssp,0))*adjinc*power(10,-6)) ss, 
                        sum((coalesce(ssip,0))*adjinc*power(10,-6)) disability, 
                        sum((coalesce(semp,0))*adjinc*power(10,-6)) sinc,
                        np,
                        iserialno from T 
                        group by iserialno, np))";
            }
            else {
                $q = "with T AS (select * from income, household, communities
                        where income.iserialno=household.serialno
                        AND communities.communityid=household.puma
                        AND communities.year = income.year
                        AND household.year= income.year
                        AND communities.name = '" . $var3 . "'
                        AND communities.year = " . $var4 . "
                        )

                        select median(pincome) from 
                        (select (retirement+interest+wages+assist+ss+disability+sinc) as pincome, iserialno 
                        from (select sum((coalesce(retp,0))*adjinc*power(10,-6)) retirement, 
                        sum((coalesce(intp,0))*adjinc*power(10,-6)) interest, 
                        sum((coalesce(WAGP,0))*adjinc*power(10,-6)) wages, 
                        sum((coalesce(pap,0))*adjinc*power(10,-6)) assist, 
                        sum((coalesce(ssp,0))*adjinc*power(10,-6)) ss, 
                        sum((coalesce(ssip,0))*adjinc*power(10,-6)) disability, 
                        sum((coalesce(semp,0))*adjinc*power(10,-6)) sinc,
                        np,
                        iserialno from T 
                        group by iserialno, np))";
            }

            $stid = oci_parse($conn, $q);
            oci_define_by_name($stid, 'MEDIAN(PINCOME)', $name);
            oci_execute($stid);
        }
        else if($var1 == 'Average Income') {
            $q = "";
            if($var3 == 'Florida') {
                $q = "with T AS (select * from income, household, communities
                        where income.iserialno=household.serialno
                        AND communities.communityid=household.puma
                        AND communities.year = income.year
                        AND household.year= income.year
                        AND communities.year = '". $var4 . "'
                        )

                        select ROUND(avg(pincome), 2) from 
                        (select (retirement+interest+wages+assist+ss+disability+sinc) as pincome, iserialno 
                        from (select sum((coalesce(retp,0))*adjinc*power(10,-6)) retirement, 
                        sum((coalesce(intp,0))*adjinc*power(10,-6)) interest, 
                        sum((coalesce(WAGP,0))*adjinc*power(10,-6)) wages, 
                        sum((coalesce(pap,0))*adjinc*power(10,-6)) assist, 
                        sum((coalesce(ssp,0))*adjinc*power(10,-6)) ss, 
                        sum((coalesce(ssip,0))*adjinc*power(10,-6)) disability, 
                        sum((coalesce(semp,0))*adjinc*power(10,-6)) sinc,
                        np,
                        iserialno from T 
                        group by iserialno, np))";
            }
            else {
                $q = "with T AS (select * from income, household, communities
                        where income.iserialno=household.serialno
                        AND communities.communityid=household.puma
                        AND communities.year = income.year
                        AND household.year= income.year
                        AND communities.name = '" . $var3 . "'
                        AND communities.year = " . $var4 . "
                        )

                        select ROUND(avg(pincome), 2) from 
                        (select (retirement+interest+wages+assist+ss+disability+sinc) as pincome, iserialno 
                        from (select sum((coalesce(retp,0))*adjinc*power(10,-6)) retirement, 
                        sum((coalesce(intp,0))*adjinc*power(10,-6)) interest, 
                        sum((coalesce(WAGP,0))*adjinc*power(10,-6)) wages, 
                        sum((coalesce(pap,0))*adjinc*power(10,-6)) assist, 
                        sum((coalesce(ssp,0))*adjinc*power(10,-6)) ss, 
                        sum((coalesce(ssip,0))*adjinc*power(10,-6)) disability, 
                        sum((coalesce(semp,0))*adjinc*power(10,-6)) sinc,
                        np,
                        iserialno from T 
                        group by iserialno, np))";
            }
        
            $stid = oci_parse($conn, $q);
            oci_define_by_name($stid, 'ROUND(AVG(PINCOME),2)', $name);
            oci_execute($stid);
        }
        else if($var1 == 'Fastest Growing Industry') {
            //ADD DIFFERENT QUERY
            $q = "";
            $varYear = $var4 + 1;
            if($var3 == 'Florida') {
                $q = "with T as
                        (select * from communities, household, person, industry
                        where person.serialno=household.serialno
                        AND person.naicsp=industry.industryid
                        AND communities.communityid=household.puma
                        AND Household.year=person.year
                        AND communities.year=person.year
                        --AND communities.name= 'Alachua County (Central)--Gainesville City (Central)'
                        AND communities.year=" . $var4 . "
                        )--OR communities.year=2013)
                        ,

                        X as
                        (select * from communities, household, person, industry
                        where person.serialno=household.serialno
                        AND person.naicsp=industry.industryid
                        AND communities.communityid=household.puma
                        AND Household.year=person.year
                        AND communities.year=person.year
                        --AND communities.name= 'Alachua County (Central)--Gainesville City (Central)'
                        AND communities.year=" . $varYear . ")

                        select n1 from ((select n1, n2, i2-i1 from (select iname n1, count(industryid)i1 from T group by industryid, iname)
                        , (select iname n2, count(industryid)i2 from X group by industryid, iname) where n1=n2) order by i2-i1 desc) 
                        where rownum = 1";
            }
            else {
                $q = "with T as
                        (select * from communities, household, person, industry
                        where person.serialno=household.serialno
                        AND person.naicsp=industry.industryid
                        AND communities.communityid=household.puma
                        AND Household.year=person.year
                        AND communities.year=person.year
                        AND communities.name= '" . $var3 . "'
                        AND communities.year=" . $var4 . "
                        )--OR communities.year=2013)
                        ,

                        X as
                        (select * from communities, household, person, industry
                        where person.serialno=household.serialno
                        AND person.naicsp=industry.industryid
                        AND communities.communityid=household.puma
                        AND Household.year=person.year
                        AND communities.year=person.year
                        AND communities.name= '" . $var3 . "'
                        AND communities.year=" . $varYear . ")

                        select n1 from ((select n1, n2, i2-i1 from (select iname n1, count(industryid)i1 from T group by industryid, iname)
                        , (select iname n2, count(industryid)i2 from X group by industryid, iname) where n1=n2) order by i2-i1 desc) 
                        where rownum = 1";
            }
            
            $stid = oci_parse($conn, $q);  
            oci_define_by_name($stid, 'N1', $name);
            oci_execute($stid);
        }
        else if($var1 == 'Percentage of Migrants') {
            $q = "";
            if($var3 == 'Florida') {
                $q = "select states.name, ROUND(Count(mig)/(select Count(mig) from ((person join household on (person.serialNo = household.serialNo and person.year 
                        = household.year)) join communities on (household.PUMA = communities.communityID and household.year= communities.year)) join states on
                        (communities.belongsTo = states.stateID and communities.year = states.year) where states.name = 'Florida' and person.year = " . $var4 . ")*100, 2) as percentMigrant
                        from ((person join household on (person.serialNo = household.serialNo and person.year = household.year)) join communities on 
                        (household.PUMA = communities.communityID and household.year= communities.year)) join states on (communities.belongsTo = 
                        states.stateID and communities.year = states.year)
                        where states.name = 'Florida' and person.year = " . $var4 . " and mig = 2
                        group by states.name";
            }
            else {
                $q = "select communities.name, ROUND(Count(mig)/(select Count(mig) from (person join household on (person.serialNo = household.serialNo and 
                        person.year = household.year)) join communities on (household.PUMA = communities.communityID and household.year= communities.year)
                        where communities.name = '" . $var3 . "' and person.year = " . $var4 . ")*100, 2) as percentMigrant
                        from (person join household on (person.serialNo = household.serialNo and person.year = household.year)) join communities on 
                        (household.PUMA = communities.communityID and household.year= communities.year)
                        where communities.name = '" . $var3 . "' and person.year = " . $var4 . " and mig = 2 
                        group by communities.name";
            }
            
            $stid = oci_parse($conn, $q);  
            oci_define_by_name($stid, "PERCENTMIGRANT", $name);
            oci_execute($stid);
        }
        else if($var1 == 'Poverty Rate') {
            $q = "";
            if($var3 == 'Florida') {
                $q = "with T AS (select * from income, household, communities
                        where income.iserialno=household.serialno
                        AND communities.communityid=household.puma
                        AND communities.year = income.year
                        AND household.year= income.year
                        AND communities.year = " . $var4 . "
                        )
                        --select (retirement+interest+wages+assist+ss+disability+sinc), iserialno 
                        select ROUND((numer/ denom)*100, 2)
                        from (select count(distinct iserialno) as numer
                        from (select sum((coalesce(retp,0))*adjinc*power(10,-6)) retirement, 
                        sum((coalesce(intp,0))*adjinc*power(10,-6)) interest, 
                        sum((coalesce(WAGP,0))*adjinc*power(10,-6)) wages, 
                        sum((coalesce(pap,0))*adjinc*power(10,-6)) assist, 
                        sum((coalesce(ssp,0))*adjinc*power(10,-6)) ss, 
                        sum((coalesce(ssip,0))*adjinc*power(10,-6)) disability, 
                        sum((coalesce(semp,0))*adjinc*power(10,-6)) sinc,
                        np,
                        iserialno from T 
                        group by iserialno, np)
                        where (retirement+interest+wages+assist+ss+disability+sinc)
                        < 
                        (POWER( np, 5)*3.7481 -
                        70.284 * power(np,4) +
                        413.55 * power(np,3) 
                        - 609.44 * power(np,2) +
                        2871.7*np + 9144.7)), (select Count(distinct serialNo) as denom from T)";
            }
            else {
                $q = " --this gives the number of households in poverty in a given community
                            with T AS (select * from income, household, communities
                            where income.iserialno=household.serialno
                            AND communities.communityid=household.puma
                            AND communities.year = income.year
                            AND household.year= income.year
                            AND communities.name = '" . $var3 . "'
                            AND communities.year = " . $var4 . "
                            )
                            select ROUND((numer/ denom)*100, 2)
                            from (select count(distinct iserialno) as numer
                            from (select sum((coalesce(retp,0))*adjinc*power(10,-6)) retirement, 
                            sum((coalesce(intp,0))*adjinc*power(10,-6)) interest, 
                            sum((coalesce(WAGP,0))*adjinc*power(10,-6)) wages, 
                            sum((coalesce(pap,0))*adjinc*power(10,-6)) assist, 
                            sum((coalesce(ssp,0))*adjinc*power(10,-6)) ss, 
                            sum((coalesce(ssip,0))*adjinc*power(10,-6)) disability, 
                            sum((coalesce(semp,0))*adjinc*power(10,-6)) sinc,
                            np,
                            iserialno, adjinc from T 
                            group by iserialno, np, adjinc)
                            where (retirement+interest+wages+assist+ss+disability+sinc)
                            < 
                            (POWER( np, 5)*3.7481 -
                            70.284 * power(np,4) +
                            413.55 * power(np,3) 
                            - 609.44 * power(np,2) +
                            2871.7*np + 9144.7)), (select Count(distinct serialNo) as denom from T)";
            }
            $stid = oci_parse($conn, $q);  
            oci_define_by_name($stid, 'ROUND((NUMER/DENOM)*100,2)', $name);
            oci_execute($stid);
        }
        else if($var1 == 'Number of Languages') {
            $q = "";
            if($var3 == 'Florida') {
                $q = "select states.name, Count(distinct person.LANP)
                        from person join HOUSEHOLD on (person.serialNo = household.serialNo and person.year = household.year)
                        join communities on (household.PUMA = communities.communityID and household.year = communities.year)
                        join states on (communities.belongsTo = states.stateID and communities.year = states.year)
                        where states.name = 'Florida' and person.year = " . $var4 . " group by states.name";
            }
            else {
                $q = "select Count(distinct person.LANP)
                    from person join HOUSEHOLD on (person.serialNo = household.serialNo and person.year = household.year)
                    join communities on (household.PUMA = communities.communityID and household.year = communities.year)
                    where communities.name = '" . $var3 . "' and person.year = " . $var4 . " group by communities.name";
            }
            
            $stid = oci_parse($conn, $q);  
            oci_define_by_name($stid, 'COUNT(DISTINCTPERSON.LANP)', $name);
            oci_execute($stid);
        }
        else if($var1 == 'Property Value') {
            $q = "";
            if($var3 == 'Florida') {
                $q = "select ROUND(Avg(coalesce(household.VALP, 0)),2)
                        from household join communities on (PUMA = communityID and communities.year = household.year) join states on 
                        (communities.BELONGSTO = states.stateID and communities.year = states.year)
                        where states.name = 'Florida' and household.year = " . $var4 . " and household.NP != 0
                        group by states.name";
            }
            else {
                $q = "select ROUND(Avg(coalesce(household.VALP, 0)),2)
                    from household join communities on (PUMA = communityID and communities.year = household.year) 
                    where communities.name = '" . $var3 . "' and household.year = " . $var4 . " and household.NP != 0 group by communities.name";
            }
            
            $stid = oci_parse($conn, $q);  
            oci_define_by_name($stid, 'ROUND(AVG(COALESCE(HOUSEHOLD.VALP,0)),2)', $name);
            oci_execute($stid);
        }
        else if($var1 == 'Most Spoken Foreign Language') {
            $q = "";
            if($var3 == 'Florida') {
                $q = "select primarylanguage.name
                        from primarylanguage join person on languageID = person.lanp join household on 
                        (person.serialNo = household.serialNo and person.year = household.year) join communities on 
                        (household.PUMA = communities.communityID and household.year = communities.year) join states on
                        (communities.belongsTo = states.stateID and communities.year = states.year)
                        where states.name = 'Florida' and states.year = " . $var4 . " group by states.name, primarylanguage.name
                        having Count(*) = (select Max(Count(*)) from primarylanguage join person on languageID = person.lanp 
                        join household on (person.serialNo = household.serialNo and person.year = household.year) join 
                        communities on (household.PUMA = communities.communityID and household.year = communities.year)
                        join states on (communities.belongsTo = states.stateID and communities.year = states.year)
                        where states.name = 'Florida' and states.year = " . $var4 . " group by languageID)";
            }
            else {
                $q = "select primarylanguage.name
                        from primarylanguage join person on languageID = person.lanp join household on 
                        (person.serialNo = household.serialNo and person.year = household.year) join communities on 
                        (household.PUMA = communities.communityID and household.year = communities.year)
                        where communities.name = '" . $var3 . "' and communities.year = " . $var4 . " group by communities.name, primarylanguage.name
                        having Count(*) = (select Max(Count(*)) from (primarylanguage join person on languageID = 
                        person.lanp) join household on (person.serialNo = household.serialNo and person.year = household.year) 
                        join communities on (household.PUMA = communities.communityID and household.year = 
                        communities.year) where communities.name = '" . $var3 . "' and 
                        communities.year = " . $var4 . " group by languageID)";
            }
            $stid = oci_parse($conn, $q);
            oci_define_by_name($stid, 'NAME', $name);
            oci_execute($stid);
        }
        else if($var1 == 'Average Age') {
            $q = "";
            if($var3 == 'Florida') {
                $q = "select states.name, ROUND(Avg(person.Agep),2)
                        from person join household on (person.serialNo = household.serialNo and  person.year = household.year)
                            join communities on (PUMA = communityID and communities.year = household.year) join states on 
                            (communities.BELONGSTO = states.stateID and communities.year = states.year)
                        where states.name = 'Florida'  and person.year = " . $var4 . "
                        group by states.name";
            }
            else {
                $q = "select communities.name, ROUND(Avg(person.Agep),2)
                        from person join household on (person.serialNo = household.serialNo and  person.year = household.year)
                        join communities on (PUMA = communityID and communities.year = household.year)
                        where communities.name = '" . $var3 . "' and person.year = " . $var4 . "
                        group by communities.name";
            }

            $stid = oci_parse($conn, $q);
            oci_define_by_name($stid, 'ROUND(AVG(PERSON.AGEP),2)', $name);
            oci_execute($stid);
        }
        while(oci_fetch($stid)) {
            $data = $name;
        }
        if(is_null($data)) {
            $data = "null";
        }
        oci_free_statement($stid);
        return $data;
    }

//----------------------QUERIES TO COMPARE TWO SERIES-------------------------------------
    function performQueryCompare($dataseries, $areaname, $year, $conn, $i, $array) {
        if($dataseries == 'Median Age') {
            $q = "";
            if($areaname == 'Florida') {
                $q = "select states.name, Median(person.Agep)
                        from person join household on (person.serialNo = household.serialNo and  person.year = household.year)
                        join communities on (PUMA = communityID and communities.year = household.year) join states on 
                        (communities.BELONGSTO = states.stateID and communities.year = states.year)
                        where states.name = 'Florida' and person.year = " . $year . " group by states.name";
            }
            else {
                 $q = "select Median(person.Agep)
                    from person join household on (person.serialNo = household.serialNo and  person.year = household.year)
                    join communities on (PUMA = communityID and communities.year = household.year)
                    where communities.name = '" . $areaname . "' and person.year = " . $year . " group by communities.name";
            }
           
            $stid = oci_parse($conn, $q);
            oci_define_by_name($stid, 'MEDIAN(PERSON.AGEP)', $name);
            oci_execute($stid);
        }
        else if($dataseries == 'Fertility Rate') {
            $q = "";
            if($areaname == 'Florida') {
                $q = "select sum(household.NOC)/(Count(*) / 2)
                        from person join household on (person.serialNo = household.serialNo and person.year = household.year)
                        join communities on (PUMA = communityID and communities.year = household.year)
                        join states on (communities.BELONGSTO = states.stateID and communities.year = states.year)
                        where states.name = 'Florida' and household.year = " . $year . " group by states.name";
            }
            else {
                 $q = "select sum(household.NOC)/(Count(*) / 2)
                    from person join household on (person.serialNo = household.serialNo and person.year = household.year)
                    join communities on (PUMA = communityID and communities.year = household.year)
                    where communities.name = '" . $areaname . "' and household.year = '" . $year . "' group by communities.name";
            }
            $stid = oci_parse($conn, $q);
            oci_define_by_name($stid, 'SUM(HOUSEHOLD.NOC)/(COUNT(*)/2)', $name);
            oci_execute($stid);
        }
        else if($dataseries == 'Median Income') {
            $q = "";
            if($areaname == 'Florida') {
                $q = "with T AS (select * from income, household, communities
                        where income.iserialno=household.serialno
                        AND communities.communityid=household.puma
                        AND communities.year = income.year
                        AND household.year= income.year
                        AND communities.year = " . $year . "
                        )

                        select median(pincome) from 
                        (select (retirement+interest+wages+assist+ss+disability+sinc) as pincome, iserialno 
                        from (select sum((coalesce(retp,0))*adjinc*power(10,-6)) retirement, 
                        sum((coalesce(intp,0))*adjinc*power(10,-6)) interest, 
                        sum((coalesce(WAGP,0))*adjinc*power(10,-6)) wages, 
                        sum((coalesce(pap,0))*adjinc*power(10,-6)) assist, 
                        sum((coalesce(ssp,0))*adjinc*power(10,-6)) ss, 
                        sum((coalesce(ssip,0))*adjinc*power(10,-6)) disability, 
                        sum((coalesce(semp,0))*adjinc*power(10,-6)) sinc,
                        np,
                        iserialno from T 
                        group by iserialno, np))";
            }
            else {
                $q = "with T AS (select * from income, household, communities
                        where income.iserialno=household.serialno
                        AND communities.communityid=household.puma
                        AND communities.year = income.year
                        AND household.year= income.year
                        AND communities.name = '" . $areaname . "'
                        AND communities.year = " . $year . "
                        )

                        select median(pincome) from 
                        (select (retirement+interest+wages+assist+ss+disability+sinc) as pincome, iserialno 
                        from (select sum((coalesce(retp,0))*adjinc*power(10,-6)) retirement, 
                        sum((coalesce(intp,0))*adjinc*power(10,-6)) interest, 
                        sum((coalesce(WAGP,0))*adjinc*power(10,-6)) wages, 
                        sum((coalesce(pap,0))*adjinc*power(10,-6)) assist, 
                        sum((coalesce(ssp,0))*adjinc*power(10,-6)) ss, 
                        sum((coalesce(ssip,0))*adjinc*power(10,-6)) disability, 
                        sum((coalesce(semp,0))*adjinc*power(10,-6)) sinc,
                        np,
                        iserialno from T 
                        group by iserialno, np))";
            }

            $stid = oci_parse($conn, $q);
            oci_define_by_name($stid, 'MEDIAN(PINCOME)', $name);
            oci_execute($stid);
        }
        else if($dataseries == 'Average Income') {
            $q = "";
            if($areaname == 'Florida') {
                $q = "with T AS (select * from income, household, communities
                        where income.iserialno=household.serialno
                        AND communities.communityid=household.puma
                        AND communities.year = income.year
                        AND household.year= income.year
                        AND communities.year = " . $year . "
                        )

                        select avg(pincome) from 
                        (select (retirement+interest+wages+assist+ss+disability+sinc) as pincome, iserialno 
                        from (select sum(coalesce(retp,0)) retirement, 
                        sum(coalesce(intp,0)) interest, 
                        sum(coalesce(WAGP,0)) wages, 
                        sum(coalesce(pap,0)) assist, 
                        sum(coalesce(ssp,0)) ss, 
                        sum(coalesce(ssip,0)) disability, 
                        sum(coalesce(semp,0))sinc,
                        np,
                        iserialno from T 
                        group by iserialno, np))";
            }
            else {
                $q = "with T AS (select * from income, household, communities
                        where income.iserialno=household.serialno
                        AND communities.communityid=household.puma
                        AND communities.year = income.year
                        AND household.year= income.year
                        AND communities.name = '" . $areaname . "'
                        AND communities.year = " . $year . "
                        )

                        select avg(pincome) from 
                        (select (retirement+interest+wages+assist+ss+disability+sinc) as pincome, iserialno 
                        from (select sum((coalesce(retp,0))*adjinc*power(10,-6)) retirement, 
                        sum((coalesce(intp,0))*adjinc*power(10,-6)) interest, 
                        sum((coalesce(WAGP,0))*adjinc*power(10,-6)) wages, 
                        sum((coalesce(pap,0))*adjinc*power(10,-6)) assist, 
                        sum((coalesce(ssp,0))*adjinc*power(10,-6)) ss, 
                        sum((coalesce(ssip,0))*adjinc*power(10,-6)) disability, 
                        sum((coalesce(semp,0))*adjinc*power(10,-6)) sinc,
                        np,
                        iserialno from T 
                        group by iserialno, np))";
            }
        
            $stid = oci_parse($conn, $q);
            oci_define_by_name($stid, 'AVG(PINCOME)', $name);
            oci_execute($stid);
        }
        else if($dataseries == 'Fastest Growing Industry') {
            //ADD DIFFERENT QUERY
            $q = "";
            $varYear = $year + 1;
            if($areaname == 'Florida') {
                $q = "with T as
                        (select * from communities, household, person, industry
                        where person.serialno=household.serialno
                        AND person.naicsp=industry.industryid
                        AND communities.communityid=household.puma
                        AND Household.year=person.year
                        AND communities.year=person.year
                        --AND communities.name= 'Alachua County (Central)--Gainesville City (Central)'
                        AND communities.year=" . $year . "
                        )--OR communities.year=2013)
                        ,

                        X as
                        (select * from communities, household, person, industry
                        where person.serialno=household.serialno
                        AND person.naicsp=industry.industryid
                        AND communities.communityid=household.puma
                        AND Household.year=person.year
                        AND communities.year=person.year
                        --AND communities.name= 'Alachua County (Central)--Gainesville City (Central)'
                        AND communities.year=" . $varYear . ")

                        select n1 from ((select n1, n2, i2-i1 from (select iname n1, count(industryid)i1 from T group by industryid, iname)
                        , (select iname n2, count(industryid)i2 from X group by industryid, iname) where n1=n2) order by i2-i1 desc) 
                        where rownum = 1";
            }
            else {
                $q = "with T as
                        (select * from communities, household, person, industry
                        where person.serialno=household.serialno
                        AND person.naicsp=industry.industryid
                        AND communities.communityid=household.puma
                        AND Household.year=person.year
                        AND communities.year=person.year
                        AND communities.name= '" . $areaname . "'
                        AND communities.year=" . $year . "
                        )--OR communities.year=2013)
                        ,

                        X as
                        (select * from communities, household, person, industry
                        where person.serialno=household.serialno
                        AND person.naicsp=industry.industryid
                        AND communities.communityid=household.puma
                        AND Household.year=person.year
                        AND communities.year=person.year
                        AND communities.name= '" . $areaname . "'
                        AND communities.year=" . $varYear . ")

                        select n1 from ((select n1, n2, i2-i1 from (select iname n1, count(industryid)i1 from T group by industryid, iname)
                        , (select iname n2, count(industryid)i2 from X group by industryid, iname) where n1=n2) order by i2-i1 desc) 
                        where rownum = 1";
            }
            
            $stid = oci_parse($conn, $q);  
            oci_define_by_name($stid, 'N1', $name);
            oci_execute($stid);
        }
        else if($dataseries == 'Percentage of Migrants') {
            // actually number of migrants
            $q = "";
            if($dataseries == 'Florida') {
                $q = "select states.name, Count(mig)/(select Count(mig) from ((person join household on (person.serialNo = household.serialNo and person.year 
                        = household.year)) join communities on (household.PUMA = communities.communityID and household.year= communities.year)) join states on
                        (communities.belongsTo = states.stateID and communities.year = states.year) where states.name = 'Florida' and person.year = " . $year . ")*100 as percentMigrant
                        from ((person join household on (person.serialNo = household.serialNo and person.year = household.year)) join communities on 
                        (household.PUMA = communities.communityID and household.year= communities.year)) join states on (communities.belongsTo = 
                        states.stateID and communities.year = states.year)
                        where states.name = 'Florida' and person.year = " . $year . " and mig = 2
                        group by states.name";
            }
            else {
                $q = "select communities.name, Count(mig)/(select Count(mig) from (person join household on (person.serialNo = household.serialNo and 
                        person.year = household.year)) join communities on (household.PUMA = communities.communityID and household.year= communities.year)
                        where communities.name = '" . $areaname . "' and person.year = " . $year . ")*100 as percentMigrant
                        from (person join household on (person.serialNo = household.serialNo and person.year = household.year)) join communities on 
                        (household.PUMA = communities.communityID and household.year= communities.year)
                        where communities.name = '" . $areaname . "' and person.year = " . $year . " and mig = 2 
                        group by communities.name";
            }
            
            $stid = oci_parse($conn, $q);  
            oci_define_by_name($stid, 'PERCENTMIGRANT', $name);
            oci_execute($stid);
        }
        else if($dataseries == 'Poverty Rate') {
            $q = "";
            if($areaname == 'Florida') {
                $q = "with T AS (select * from income, household, communities
                        where income.iserialno=household.serialno
                        AND communities.communityid=household.puma
                        AND communities.year = income.year
                        AND household.year= income.year
                        AND communities.year = " . $year . "
                        )
                        --select (retirement+interest+wages+assist+ss+disability+sinc), iserialno 
                        select (numer/ denom)*100
                        from (select count(distinct iserialno) as numer
                        from (select sum((coalesce(retp,0))*adjinc*power(10,-6)) retirement, 
                        sum((coalesce(intp,0))*adjinc*power(10,-6)) interest, 
                        sum((coalesce(WAGP,0))*adjinc*power(10,-6)) wages, 
                        sum((coalesce(pap,0))*adjinc*power(10,-6)) assist, 
                        sum((coalesce(ssp,0))*adjinc*power(10,-6)) ss, 
                        sum((coalesce(ssip,0))*adjinc*power(10,-6)) disability, 
                        sum((coalesce(semp,0))*adjinc*power(10,-6)) sinc,
                        np,
                        iserialno from T 
                        group by iserialno, np)
                        where (retirement+interest+wages+assist+ss+disability+sinc)
                        < 
                        (POWER( np, 5)*3.7481 -
                        70.284 * power(np,4) +
                        413.55 * power(np,3) 
                        - 609.44 * power(np,2) +
                        2871.7*np + 9144.7)), (select Count(distinct serialNo) as denom from T)";
            }
            else {
                $q = " --this gives the number of households in poverty in a given community
                            with T AS (select * from income, household, communities
                            where income.iserialno=household.serialno
                            AND communities.communityid=household.puma
                            AND communities.year = income.year
                            AND household.year= income.year
                            AND communities.name = '" . $areaname . "'
                            AND communities.year = " . $year . "
                            )
                            select (numer/ denom)*100
                            from (select count(distinct iserialno) as numer
                            from (select sum((coalesce(retp,0))*adjinc*power(10,-6)) retirement, 
                            sum((coalesce(intp,0))*adjinc*power(10,-6)) interest, 
                            sum((coalesce(WAGP,0))*adjinc*power(10,-6)) wages, 
                            sum((coalesce(pap,0))*adjinc*power(10,-6)) assist, 
                            sum((coalesce(ssp,0))*adjinc*power(10,-6)) ss, 
                            sum((coalesce(ssip,0))*adjinc*power(10,-6)) disability, 
                            sum((coalesce(semp,0))*adjinc*power(10,-6)) sinc,
                            np,
                            iserialno, adjinc from T 
                            group by iserialno, np, adjinc)
                            where (retirement+interest+wages+assist+ss+disability+sinc)
                            < 
                            (POWER( np, 5)*3.7481 -
                            70.284 * power(np,4) +
                            413.55 * power(np,3) 
                            - 609.44 * power(np,2) +
                            2871.7*np + 9144.7)), (select Count(distinct serialNo) as denom from T)";
            }
            $stid = oci_parse($conn, $q);  
            oci_define_by_name($stid, '(NUMER/DENOM)*100', $name);
            oci_execute($stid);
        }
        else if($dataseries == 'Number of Languages') {
            $q = "";
            if($areaname == 'Florida') {
                $q = "select states.name, Count(distinct person.LANP)
                        from person join HOUSEHOLD on (person.serialNo = household.serialNo and person.year = household.year)
                        join communities on (household.PUMA = communities.communityID and household.year = communities.year)
                        join states on (communities.belongsTo = states.stateID and communities.year = states.year)
                        where states.name = 'Florida' and person.year = " . $year . " group by states.name";
            }
            else {
                $q = "select Count(distinct person.LANP)
                    from person join HOUSEHOLD on (person.serialNo = household.serialNo and person.year = household.year)
                    join communities on (household.PUMA = communities.communityID and household.year = communities.year)
                    where communities.name = '" . $areaname . "' and person.year = " . $year . " group by communities.name";
            }
            
            $stid = oci_parse($conn, $q);  
            oci_define_by_name($stid, 'COUNT(DISTINCTPERSON.LANP)', $name);
            oci_execute($stid);
        }
        else if($dataseries == 'Property Value') {
            $q = "";
            if($areaname == 'Florida') {
                $q = "select Avg(coalesce(household.VALP, 0))
                        from household join communities on (PUMA = communityID and communities.year = household.year) join states on 
                        (communities.BELONGSTO = states.stateID and communities.year = states.year)
                        where states.name = 'Florida' and household.year = " . $year . " and household.NP != 0
                        group by states.name";
            }
            else {
                $q = "select Avg(coalesce(household.VALP, 0))
                    from household join communities on (PUMA = communityID and communities.year = household.year) 
                    where communities.name = '" . $areaname . "' and household.year = " . $year . " and household.NP != 0 group by communities.name";
            }
            
            $stid = oci_parse($conn, $q);  
            oci_define_by_name($stid, 'AVG(COALESCE(HOUSEHOLD.VALP,0))', $name);
            oci_execute($stid);
        }
        else if($dataseries == 'Most Spoken Foreign Language') {
            $q = "";
            if($areaname == 'Florida') {
                $q = "select primarylanguage.name
                        from primarylanguage join person on languageID = person.lanp join household on 
                        (person.serialNo = household.serialNo and person.year = household.year) join communities on 
                        (household.PUMA = communities.communityID and household.year = communities.year) join states on
                        (communities.belongsTo = states.stateID and communities.year = states.year)
                        where states.name = 'Florida' and states.year = " . $year . " group by states.name, primarylanguage.name
                        having Count(*) = (select Max(Count(*)) from primarylanguage join person on languageID = person.lanp 
                        join household on (person.serialNo = household.serialNo and person.year = household.year) join 
                        communities on (household.PUMA = communities.communityID and household.year = communities.year)
                        join states on (communities.belongsTo = states.stateID and communities.year = states.year)
                        where states.name = 'Florida' and states.year = " . $year . " group by languageID)";
            }
            else {
                $q = "select primarylanguage.name
                        from primarylanguage join person on languageID = person.lanp join household on 
                        (person.serialNo = household.serialNo and person.year = household.year) join communities on 
                        (household.PUMA = communities.communityID and household.year = communities.year)
                        where communities.name = '" . $areaname . "' and communities.year = " . $year . " group by communities.name, primarylanguage.name
                        having Count(*) = (select Max(Count(*)) from (primarylanguage join person on languageID = 
                        person.lanp) join household on (person.serialNo = household.serialNo and person.year = household.year) 
                        join communities on (household.PUMA = communities.communityID and household.year = 
                        communities.year) where communities.name = '" . $areaname . "' and 
                        communities.year = " . $year . " group by languageID)";
            }

            $stid = oci_parse($conn, $q);
            oci_define_by_name($stid, 'NAME', $name);
            oci_execute($stid);
        }
        else if($dataseries == 'Average Age') {
            $q = "";
            if($var3 == 'Florida') {
                $q = "select states.name, Avg(person.Agep)
                        from person join household on (person.serialNo = household.serialNo and  person.year = household.year)
                            join communities on (PUMA = communityID and communities.year = household.year) join states on 
                            (communities.BELONGSTO = states.stateID and communities.year = states.year)
                        where states.name = 'Florida'  and person.year = " . $year . "
                        group by states.name";
            }
            else {
                $q = "select communities.name, Avg(person.Agep)
                        from person join household on (person.serialNo = household.serialNo and  person.year = household.year)
                        join communities on (PUMA = communityID and communities.year = household.year)
                        where communities.name = '" . $areaname . "' and person.year = " . $year . "
                        group by communities.name";
            }

            $stid = oci_parse($conn, $q);
            oci_define_by_name($stid, 'AVG(PERSON.AGEP)', $name);
            oci_execute($stid);
        }
        while(oci_fetch($stid)) {
            $array[$i] = $name;
        }
        
        oci_free_statement($stid);
        return $array[$i];
    }

//--------------------CORRELATION FUNCTIONS-----------------------
    function Correlation($arr1, $arr2) {        
        $correlation = 0;

        $k = SumProductMeanDeviation($arr1, $arr2);
        $ssmd1 = SumSquareMeanDeviation($arr1);
        $ssmd2 = SumSquareMeanDeviation($arr2);

        $product = $ssmd1 * $ssmd2;

        $res = sqrt($product);

        $correlation = $k / $res;

        return $correlation;
    }

    function SumProductMeanDeviation($arr1, $arr2) {
        $sum = 0;

        $num = count($arr1);

        for($i=0; $i<$num; $i++)
        {
            $sum = $sum + ProductMeanDeviation($arr1, $arr2, $i);
        }

        return $sum;
    }

    function ProductMeanDeviation($arr1, $arr2, $item) {
        return (MeanDeviation($arr1, $item) * MeanDeviation($arr2, $item));
    }

    function SumSquareMeanDeviation($arr) {
        $sum = 0;

        $num = count($arr);

        for($i=0; $i<$num; $i++)
        {
            $sum = $sum + SquareMeanDeviation($arr, $i);
        }

        return $sum;
    }

    function SquareMeanDeviation($arr, $item) {
        return MeanDeviation($arr, $item) * MeanDeviation($arr, $item);
    }

    function SumMeanDeviation($arr) {
        $sum = 0;

        $num = count($arr);

        for($i=0; $i<$num; $i++)
        {
            $sum = $sum + MeanDeviation($arr, $i);
        }

        return $sum;
    }

    function MeanDeviation($arr, $item) {
        $average = Average($arr);

        return $arr[$item] - $average;
    }    

    function Average($arr) {
        $sum = Sum($arr);
        $num = count($arr);

        return $sum/$num;
    }

    function Sum($arr) {
        return array_sum($arr);
    }

    // Close the Oracle connection
    oci_close($conn);
?>