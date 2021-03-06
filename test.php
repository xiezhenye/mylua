<?php

error_reporting(E_ALL);

$mode = isset($argv[1]) ? $argv[1] : "";
$host = isset($argv[2]) ? $argv[2] : "localhost";
$user = isset($argv[3]) ? $argv[3] : "localuser";
$pass = isset($argv[4]) ? $argv[4] : "localpass";
$db   = isset($argv[5]) ? $argv[5] : "localuser";

mysql_connect($host, $user, $pass);
mysql_select_db($db);
mysql_set_charset("utf8");

$test_a = array();

// not constant arg
$test_a[] = test_ext('uid', 'sid', 'FROM mylua_test LIMIT 1', null, 1123, 'Can\'t initialize function \'mylua\'; Not constant argument.');

// invalid type
$invalid_type_code_a = array(0, 1, -1, 'true', 'false');
$invalid_type_arg_a = array(0, 1, -1, 'true', 'false');
foreach ($invalid_type_code_a as $code) {
    $test_a[] = test($code, q('{}'), null, 1123, 'Can\'t initialize function \'mylua\'; Wrong argument type.');
}
foreach ($invalid_type_arg_a as $arg) {
    $test_a[] = test('""', $arg, null, 1123, 'Can\'t initialize function \'mylua\'; Wrong argument type.');
}

// null
$test_a[] = test('null', q('{}'), null, 1123, 'Can\'t initialize function \'mylua\'; Not constant argument.');
$test_a[] = test('""', 'null', null, 1123, 'Can\'t initialize function \'mylua\'; Not constant argument.');

// invalid code
$test_a[] = test(q('foo bar'), q('{}'), error('lua_cpcall(pmylua): LUA_ERRRUN: [string foo bar]:1: \'=\' expected near \'bar\''));

// invalid arg json
$test_a[] = test(q(''), q(''), error('lua_cpcall(pmylua): LUA_ERRRUN: Expected value but found T_END at character 1'));

//
$test_a[] = test(q(''), q('{}'), ok());
$test_a[] = test(q('return 1'), q('{}'), ok(1));
$test_a[] = test(q('return mylua.arg'), q('{"0":[0,1,true,false,"foo"]}'), ok(array("0" => array(0, 1, true, false, "foo"))));


// mylua.get_memory_limit_bytes
$test_a[] = test(q('return mylua.get_memory_limit_bytes()'), q('{}'), ok(1024 * 1024));


// mylua.set_memory_limit_bytes
$test_a[] = test(q('mylua.set_memory_limit_bytes(1234 * 1024); return mylua.get_memory_limit_bytes()'), q('{}'), ok(1234 * 1024));
$test_a[] = test(q('mylua.set_memory_limit_bytes(1)'), q('{}'), error('lua_cpcall(pmylua): LUA_ERRMEM: not enough memory'));

// mylua.set_memory_limit_bytes (wrong argument)
$args_invalid = array('nil' => 'nil', 'boolean' => 'true', 'string' => '""', 'table' => '{}', 'function' => 'function () return 1 end');
foreach ($args_invalid as $type => $invalid) {
    $code = "
        mylua.set_memory_limit_bytes($invalid)
    ";
    $test_a[] = test(q($code), q('{}'), error('lua_cpcall(pmylua): LUA_ERRRUN: [string ...]:2: bad argument #1 to \'set_memory_limit_bytes\' (number expected, got '.$type.')'));
}


// disabled libraries
$test_a[] = test(q('os.exit()'), q('{}'), error('lua_cpcall(pmylua): LUA_ERRRUN: [string os.exit()]:1: attempt to call field \'exit\' (a nil value)'));


// too long argument and return.
$str = str_repeat('a', 256 * 256 + 1);
$code = 'return mylua.arg.str';
$test_a[] = test(q($code), q(json_encode(array('str' => $str))), ok($str));


// mylua.init_table
$code = 'mylua.init_table("'.$db.'", "mylua_test", "uid", "uid", "sid")';
$test_a[] = test(q($code), q('{}'), ok());

// mylua.init_table (wrong argument)
$args_init_table_default = array("'{$db}'", '"mylua_test"', '"uid"', '"uid"', '"sid"');
$args_invalid = array('nil' => 'nil', 'boolean' => 'true', 'number' => '0', 'table' => '{}', 'function' => 'function () return 1 end');
foreach ($args_invalid as $type => $invalid) {
    foreach ($args_init_table_default as $i => $_) {
        $copy = $args_init_table_default;
        $copy[$i] = $invalid;
        $code = "\nmylua.init_table({$copy[0]}, {$copy[1]}, {$copy[2]}, {$copy[3]}, {$copy[4]})";
        $test_a[] = test(q($code), q('{}'), error('lua_cpcall(pmylua): LUA_ERRRUN: [string ...]:2: bad argument #'.($i + 1).' to \'init_table\' (string expected, got '.$type.')'));
    }
}
$args_invalid = array('', 'a');
foreach ($args_invalid as $invalid) {
    foreach ($args_init_table_default as $i => $_) {
        $copy = $args_init_table_default;
        $copy[$i] = "'$invalid'";
        $code = "\nmylua.init_table({$copy[0]}, {$copy[1]}, {$copy[2]}, {$copy[3]}, {$copy[4]})";
        if ($i == 0) {
            $test_a[] = test(q($code), q('{}'), null, 1146, 'Table \''.$invalid.'.mylua_test\' doesn\'t exist');
        } else if ($i == 1) {
            $test_a[] = test(q($code), q('{}'), null, 1146, 'Table \''.$db.'.'.$invalid.'\' doesn\'t exist');
        } else if ($i == 2) {
            $test_a[] = test(q($code), q('{}'), error('lua_cpcall(pmylua): LUA_ERRRUN: mylua_init_table: key'));
        } else {
            $test_a[] = test(q($code), q('{}'), error('lua_cpcall(pmylua): LUA_ERRRUN: mylua_init_table: field'));
        }
    }
}

$code = '
    mylua.init_table("'.$db.'", "mylua_test", "uid", "sid")
';
$test_a[] = test(q($code), q('{}'), error('lua_cpcall(pmylua): LUA_ERRRUN: mylua_init_table: strcmp(key->key_part[i].field->field_name, field->field_name) == 0'));

// mylua.init_table (too few argument)
$code = 'mylua.init_table("'.$db.'", "mylua_test", "uid")';
$test_a[] = test(q($code), q('{}'), error('lua_cpcall(pmylua): LUA_ERRRUN: mylua_init_table: argc >= 4'));

// mylua.init_table (multi call)
$code = "
    local s1 = pcall(mylua.init_table, '$db', 'mylua_test', 'uid', 'uid', 'sid')
    local s2 = pcall(mylua.init_table, '$db', 'mylua_test', 'uid', 'uid', 'sid')
    return { s1, s2 }
";
$test_a[] = test(q($code), q('{}'), ok(array(true, false)));

foreach ($args_init_table_default as $i => $_) {
    $copy = $args_init_table_default;
    $copy[$i] = '""';
    $code = "
        local s1 = pcall(mylua.init_table, {$copy[0]}, {$copy[1]}, {$copy[2]}, {$copy[3]}, {$copy[4]})
        local s2 = pcall(mylua.init_table, '$db', 'mylua_test', 'uid', 'uid', 'sid')
        return { s1, s2 }
    ";
    if ($i == 0) {
        $test_a[] = test(q($code), q('{}'), null, 1146, 'Table \'.mylua_test\' doesn\'t exist');
    } else if ($i == 1) {
        $test_a[] = test(q($code), q('{}'), null, 1146, 'Table \''.$db.'.\' doesn\'t exist');
    } else {
        $test_a[] = test(q($code), q('{}'), ok(array(false, false)));
    }
}


// mylua.init_extra_field
$code = '
    mylua.init_table("'.$db.'", "mylua_test", "uid", "uid", "sid")
    mylua.init_extra_field("ex1")
';
$test_a[] = test(q($code), q('{}'), ok());

$code = '
    mylua.init_table("'.$db.'", "mylua_test", "uid", "uid", "sid")
    mylua.init_extra_field("ex1", "ex2")
';
$test_a[] = test(q($code), q('{}'), ok());

// mylua.init_extra_field (wrong argument)
$args_invalid = array('nil' => 'nil', 'boolean' => 'true', 'number' => '0', 'table' => '{}', 'function' => 'function () return 1 end');
foreach ($args_invalid as $type => $invalid) {
    $code = "
        mylua.init_table('{$db}', 'mylua_test', 'uid', 'uid', 'sid')
        mylua.init_extra_field({$invalid})
    ";
    $test_a[] = test(q($code), q('{}'), error('lua_cpcall(pmylua): LUA_ERRRUN: [string ...]:3: bad argument #1 to \'init_extra_field\' (string expected, got '.$type.')'));
}


// mylua.index_read_map
$code = '
    mylua.init_table("'.$db.'", "mylua_test", "uid", "uid", "sid")
    return mylua.index_read_map(mylua.HA_READ_KEY_OR_NEXT, 0, 0) == 0
';
$test_a[] = test(q($code), q('{}'), ok(true));

// mylua.index_read_map (wrong argument)
$args_index_read_map_default = array('mylua.HA_READ_KEY_OR_NEXT', '0', '0');
$args_invalid = array('nil' => 'nil', 'boolean' => 'true', 'string' => '""', 'table' => '{}', 'function' => 'function () return 1 end');
foreach ($args_invalid as $type => $invalid) {
    foreach ($args_index_read_map_default as $i => $_) {
        $copy = $args_index_read_map_default;
        $copy[$i] = $invalid;
        $code = "
            mylua.init_table('{$db}', 'mylua_test', 'uid', 'uid', 'sid')
            mylua.index_read_map({$copy[0]}, {$copy[1]}, {$copy[2]})
        ";
        if ($i == 0) {
            $test_a[] = test(q($code), q('{}'), error('lua_cpcall(pmylua): LUA_ERRRUN: [string ...]:3: bad argument #1 to \'index_read_map\' (number expected, got '.$type.')'));
        } else {
            $test_a[] = test(q($code), q('{}'), error('lua_cpcall(pmylua): LUA_ERRRUN: mylua_index_read_map: lua_type(lua, argi) == LUA_TNUMBER'));
        }
    }
}

$args_invalid = array(pow(2, 31), pow(2, 33), pow(2, 45), pow(2, 47), pow(2, 63), pow(2, 65));
foreach ($args_invalid as $type => $invalid) {
    foreach ($args_index_read_map_default as $i => $_) {
        if ($i == 0) continue;
        $copy = $args_index_read_map_default;
        $copy[$i] = $invalid;
        $code = "
            mylua.init_table('{$db}', 'mylua_test', 'uid', 'uid', 'sid')
            mylua.index_read_map(mylua.HA_READ_KEY_OR_NEXT, {$copy[1]}, {$copy[2]})
        ";
        $test_a[] = test(q($code), q('{}'), error('lua_cpcall(pmylua): LUA_ERRRUN: mylua_index_read_map: -0x7fffffff <= dbl && dbl <= 0x7fffffff'));
    }
}

// mylua.index_read_map (wrong timing call)
$code = '
mylua.index_read_map(mylua.HA_READ_KEY_OR_NEXT, 0, 0)
';
$test_a[] = test(q($code), q('{}'), error('lua_cpcall(pmylua): LUA_ERRRUN: mylua_index_read_map: mylua_area->init_table_done'));

foreach ($args_init_table_default as $i => $_) {
    $copy = $args_init_table_default;
    $copy[$i] = '""';
    $code = "
        pcall(mylua.init_table, {$copy[0]}, {$copy[1]}, {$copy[2]}, {$copy[3]}, {$copy[4]})
        mylua.index_read_map(mylua.HA_READ_KEY_OR_NEXT, 0, 0)
    ";
    if ($i == 0) {
        $test_a[] = test(q($code), q('{}'), null, 1146, 'Table \'.mylua_test\' doesn\'t exist');
    } else if ($i == 1) {
        $test_a[] = test(q($code), q('{}'), null, 1146, 'Table \''.$db.'.\' doesn\'t exist');
    } else {
        $test_a[] = test(q($code), q('{}'), error('lua_cpcall(pmylua): LUA_ERRRUN: mylua_index_read_map: mylua_area->init_table_done'));
    }
}

// mylua.index_read_map (not match)
$code = '
    mylua.init_table("'.$db.'", "mylua_test", "uid", "uid", "sid")
    return mylua.index_read_map(mylua.HA_READ_KEY_OR_PREV, 0, 0) == mylua.HA_ERR_KEY_NOT_FOUND
';
$test_a[] = test(q($code), q('{}'), ok(true));

// mylua.index_read_map (wrong argument count)
$code = '
    mylua.init_table("'.$db.'", "mylua_test", "uid", "uid", "sid")
    mylua.index_read_map(mylua.HA_READ_KEY_OR_NEXT, 0)
';
$test_a[] = test(q($code), q('{}'), error('lua_cpcall(pmylua): LUA_ERRRUN: mylua_index_read_map: mylua_area->using_key_parts == fld_c'));

$code = '
    mylua.init_table("'.$db.'", "mylua_test", "uid", "uid", "sid")
    mylua.index_read_map(mylua.HA_READ_KEY_OR_NEXT, 0, 0, 0)
';
$test_a[] = test(q($code), q('{}'), error('lua_cpcall(pmylua): LUA_ERRRUN: mylua_index_read_map: mylua_area->using_key_parts == fld_c'));


// mylua.index_prev
$code = '
    mylua.init_table("'.$db.'", "mylua_test", "uid", "uid", "sid")
    mylua.index_read_map(mylua.HA_READ_KEY_OR_NEXT, 1, 10)
    return mylua.index_prev() == 0
';
$test_a[] = test(q($code), q('{}'), ok(true));

$code = '
    mylua.init_table("'.$db.'", "mylua_test", "uid", "uid", "sid")
    mylua.index_read_map(mylua.HA_READ_KEY_OR_NEXT, 0, 0)
    return mylua.index_prev() == mylua.HA_ERR_END_OF_FILE
';
$test_a[] = test(q($code), q('{}'), ok(true));

// mylua.index_prev (wrong timing call)
$code = "
    return mylua.index_prev() == 0
";
$test_a[] = test(q($code), q('{}'), error('lua_cpcall(pmylua): LUA_ERRRUN: mylua_index_prev: mylua_area->index_read_map_done'));


// mylua.index_next
$code = '
    mylua.init_table("'.$db.'", "mylua_test", "uid", "uid", "sid")
    mylua.index_read_map(mylua.HA_READ_KEY_OR_NEXT, 0, 0)
    return mylua.index_next() == 0
';
$test_a[] = test(q($code), q('{}'), ok(true));

$code = '
    mylua.init_table("'.$db.'", "mylua_test", "uid", "uid", "sid")
    mylua.index_read_map(mylua.HA_READ_KEY_OR_NEXT, 2147483647, 2147483647)
    return mylua.index_next() == mylua.HA_ERR_END_OF_FILE
';
$test_a[] = test(q($code), q('{}'), ok(true));

// mylua.index_next (wrong timing call)
$code = "
    return mylua.index_next() == 0
";
$test_a[] = test(q($code), q('{}'), error('lua_cpcall(pmylua): LUA_ERRRUN: mylua_index_next: mylua_area->index_read_map_done'));


// mylua.val_int
$code = '
    mylua.init_table("'.$db.'", "mylua_test", "uid", "uid", "sid")
    mylua.index_read_map(mylua.HA_READ_KEY_OR_NEXT, 1, 1)
    return { mylua.val_int("uid"), mylua.val_int("sid") }
';
$test_a[] = test(q($code), q('{}'), ok(array(1, 1)));

// mylua.val_int (wrong timing call)
$code = '
    mylua.init_table("'.$db.'", "mylua_test", "uid", "uid", "sid")
    return { mylua.val_int("uid"), mylua.val_int("sid") }
';
$test_a[] = test(q($code), q('{}'), error('lua_cpcall(pmylua): LUA_ERRRUN: mylua_val_int: mylua_area->index_read_map_done'));

$code = '
    return { mylua.val_int("uid"), mylua.val_int("sid") }
';
$test_a[] = test(q($code), q('{}'), error('lua_cpcall(pmylua): LUA_ERRRUN: mylua_val_int: mylua_area->index_read_map_done'));

// mylua.val_int (end of file)
$code = '
    mylua.init_table("'.$db.'", "mylua_test", "uid", "uid", "sid")
    mylua.index_read_map(mylua.HA_READ_KEY_OR_PREV, 0, 0)
    return { mylua.index_prev() == mylua.HA_ERR_END_OF_FILE, mylua.val_int("uid"), mylua.val_int("sid") }
';
$test_a[] = test(q($code), q('{}'), ok(array(true, 1, 1)));

// mylua.val_int (wrong argument)
$args_invalid = array('nil' => 'nil', 'boolean' => 'true', 'number' => 0, 'table' => '{}', 'function' => 'function () return 1 end');
foreach ($args_invalid as $type => $invalid) {
    $copy[$i] = $invalid;
    $code = "
        mylua.init_table('$db', 'mylua_test', 'uid', 'uid', 'sid')
        mylua.index_read_map(mylua.HA_READ_KEY_OR_NEXT, 0, 0)
        mylua.val_int($invalid)
    ";
    $test_a[] = test(q($code), q('{}'), error('lua_cpcall(pmylua): LUA_ERRRUN: [string ...]:4: bad argument #1 to \'val_int\' (string expected, got '.$type.')'));
}

$code = '
    mylua.init_table("'.$db.'", "mylua_test", "uid", "uid", "sid")
    mylua.index_read_map(mylua.HA_READ_KEY_OR_NEXT, 1, 1)
    return mylua.val_int("")
';
$test_a[] = test(q($code), q('{}'), error('lua_cpcall(pmylua): LUA_ERRRUN: mylua_val_int: field'));

// mylua.val_int (wrong argument count)
$code = '
    mylua.init_table("'.$db.'", "mylua_test", "uid", "uid", "sid")
    mylua.index_read_map(mylua.HA_READ_KEY_OR_NEXT, 1, 1)
    return mylua.val_int()
';
$test_a[] = test(q($code), q('{}'), error('lua_cpcall(pmylua): LUA_ERRRUN: mylua_val_int: argc == 1'));

// mylua.val_int (big number)
$code = '
    mylua.init_table("'.$db.'", "mylua_test", "uid", "uid", "sid")
    mylua.index_read_map(mylua.HA_READ_KEY_OR_PREV, 2147483647, 2147483647)
    return { mylua.val_int("uid"), mylua.val_int("sid") }
';
$test_a[] = test(q($code), q('{}'), ok(array(2147483647, 2147483646)));


// complex case
$code = '
    local t = {}
    mylua.init_table("'.$db.'", "mylua_test", "uid", "uid", "sid")
    mylua.init_extra_field("ex1", "ex2")
    mylua.index_read_map(mylua.HA_READ_KEY_OR_NEXT, 1, 1)
    table.insert(t, mylua.val_int("uid"))
    table.insert(t, mylua.val_int("sid"))
    table.insert(t, mylua.val_int("ex1"))
    table.insert(t, mylua.val_int("ex2"))
    mylua.index_next()
    table.insert(t, mylua.val_int("uid"))
    table.insert(t, mylua.val_int("sid"))
    table.insert(t, mylua.val_int("ex1"))
    table.insert(t, mylua.val_int("ex2"))
    mylua.index_prev()
    table.insert(t, mylua.val_int("uid"))
    table.insert(t, mylua.val_int("sid"))
    table.insert(t, mylua.val_int("ex1"))
    table.insert(t, mylua.val_int("ex2"))
    mylua.index_read_map(mylua.HA_READ_KEY_OR_PREV, 9, 9999)
    table.insert(t, mylua.val_int("uid"))
    table.insert(t, mylua.val_int("sid"))
    table.insert(t, mylua.val_int("ex1"))
    table.insert(t, mylua.val_int("ex2"))
    mylua.index_prev()
    table.insert(t, mylua.val_int("uid"))
    table.insert(t, mylua.val_int("sid"))
    table.insert(t, mylua.val_int("ex1"))
    table.insert(t, mylua.val_int("ex2"))
    mylua.index_next()
    table.insert(t, mylua.val_int("uid"))
    table.insert(t, mylua.val_int("sid"))
    table.insert(t, mylua.val_int("ex1"))
    table.insert(t, mylua.val_int("ex2"))
    return t
';
$test_a[] = test(q($code), q('{}'), ok(array(1, 1, 10, 100, 1, 10, 100, 1000, 1, 1, 10, 100, 9, 9000, 90000, 900000, 9, 900, 9000, 90000, 9, 9000, 90000, 900000)));


// use sub part of key
$code = '
    mylua.init_table("'.$db.'", "mylua_test", "uid", "uid")
    mylua.index_read_map(mylua.HA_READ_KEY_OR_NEXT, 1)
    return mylua.val_int("uid")
';
$test_a[] = test(q($code), q('{}'), ok(1));


// extra case
$code = '
    mylua.init_table("'.$db.'", "mylua_test2", "PRIMARY", "rid")
    mylua.index_read_map(mylua.HA_READ_KEY_OR_NEXT, 1)
    return mylua.val_int("rid")
';
$test_a[] = test(q($code), q('{}'), ok(1));

$key_a = array('stiny' => 10, 'ssmall' => 100, 'smedium' => 1000, 'sbig' => 10000, 'sint' => 11, 'utiny' => 10, 'usmall' => 100, 'umedium' => 1000, 'ubig' => 10000, 'uint' => 11);
foreach ($key_a as $key => $res) {
    $code = '
        mylua.init_table("'.$db.'", "mylua_test2", "'.$key.'", "'.$key.'")
        mylua.index_read_map(mylua.HA_READ_KEY_OR_NEXT, 1)
        return mylua.val_int("'.$key.'")
    ';
    $test_a[] = test(q($code), q('{}'), ok($res));
}

$code = '
    mylua.init_table("'.$db.'", "mylua_test2", "txt", "txt")
    mylua.index_read_map(mylua.HA_READ_KEY_OR_NEXT, "txt1")
    return mylua.val_int("txt")
';
$test_a[] = test(q($code), q('{}'), error('lua_cpcall(pmylua): LUA_ERRRUN: mylua_index_read_map: 0 && mylua_area->key->key_part[i].type'));

$code = '
    mylua.init_table("'.$db.'", "mylua_test2", "blb", "blb")
    mylua.index_read_map(mylua.HA_READ_KEY_OR_NEXT, "blb1")
    return mylua.val_int("blb")
';
$test_a[] = test(q($code), q('{}'), error('lua_cpcall(pmylua): LUA_ERRRUN: mylua_index_read_map: 0 && mylua_area->key->key_part[i].type'));

//// test failed. nullable key is not supported yet.
//$code = '
//    local t = {}
//    mylua.init_table("'.$db.'", "mylua_test2", "nil", "nil")
//    mylua.index_read_map(mylua.HA_READ_KEY_OR_NEXT, 3)
//    table.insert(t, mylua.val_int("nil"))
//    mylua.index_next()
//    table.insert(t, mylua.val_int("nil"))
//    mylua.index_next()
//    table.insert(t, mylua.val_int("nil"))
//    return t
//';
//$test_a[] = test(q($code), q('{}'), ok(array(3, 0, 5)));


//
if ($mode === "run") {
    $re = create_table();
    $re = run($test_a);
} else {
    print "usage: php ".basename(__FILE__)." run [ <host> <user> <pass> <db> ]\n";
    $re = 1;
}

exit($re);


//
function create_table() {
    $re = mysql_query("
        CREATE TEMPORARY TABLE mylua_test (
            uid INT NOT NULL,
            sid INT NOT NULL,
            ex1 INT NOT NULL,
            ex2 INT NOT NULL,
            PRIMARY KEY (sid),
            KEY (uid, sid)
        ) Engine=InnoDB;
    ");
    if (!$re) {
        print "error: ".__FUNCTION__.": (".mysql_errno().") ".mysql_error()."\n";
        return 1;
    }
    $a = array();
    for ($uid = 1; $uid <= 9; ++$uid) {
        for ($rate = 1; $rate <= 10000; $rate *= 10) {
            $a[] = "(".intval($uid).",".intval($uid * $rate).",".intval($uid * $rate * 10).",".intval($uid * $rate * 100).")";
        }
    }
    $a[] = "(".intval(pow(2, 31) - 1).",".intval(pow(2, 31) - 2).",".intval(pow(2, 31) - 3).",".intval(pow(2, 31) - 4).")";
    $re = mysql_query("
        INSERT mylua_test (uid, sid, ex1, ex2)
        VALUES ".implode(",", $a)."
    ");
    if (!$re) {
        print "error: ".__FUNCTION__.": (".mysql_errno().") ".mysql_error()."\n";
        return 1;
    }

    $re = mysql_query("
        CREATE TEMPORARY TABLE mylua_test2 (
            rid    INT NOT NULL,
            stiny   TINYINT NOT NULL,
            ssmall  SMALLINT NOT NULL,
            smedium MEDIUMINT NOT NULL,
            sbig    BIGINT NOT NULL,
            sint   INT NOT NULL,
            txt    TEXT NOT NULL,
            utiny   TINYINT UNSIGNED NOT NULL,
            usmall  SMALLINT UNSIGNED NOT NULL,
            umedium MEDIUMINT UNSIGNED NOT NULL,
            ubig    BIGINT UNSIGNED NOT NULL,
            uint   INT UNSIGNED NOT NULL,
            blb    BLOB NOT NULL,
            nil    INT NULL,
            PRIMARY KEY (rid),
            KEY (stiny),
            KEY (ssmall),
            KEY (smedium),
            KEY (sbig),
            KEY (sint),
            KEY (txt(10)),
            KEY (utiny),
            KEY (usmall),
            KEY (umedium),
            KEY (ubig),
            KEY (uint),
            KEY (blb(10)),
            KEY (nil)
        ) Engine=MyISAM;
    ");
    if (!$re) {
        print "error: ".__FUNCTION__.": (".mysql_errno().") ".mysql_error()."\n";
        return 1;
    }
    $a = array();
    for ($rid = 1; $rid <= 9; ++$rid) {
        $b = array();
        $b["rid"] = intval($rid);
        $b["stiny"] = intval($rid * 10);
        $b["ssmall"] = intval($rid * 100);
        $b["smeidum"] = intval($rid * 1000);
        $b["sbig"] = intval($rid * 10000);
        $b["sint"] = intval($rid * 10 + 1);
        $b["txt"] = q("txt".$rid);
        $b["utiny"] = intval($rid * 10);
        $b["usmall"] = intval($rid * 100);
        $b["umeidum"] = intval($rid * 1000);
        $b["ubig"] = intval($rid * 10000);
        $b["uint"] = intval($rid * 10 + 1);
        $b["blb"] = q("blb".$rid);
        $b["nil"] = $rid % 2 ? $rid : "NULL";
        $a[] = "(".implode(",", $b).")";
    }
    $re = mysql_query("
        INSERT mylua_test2 (rid, stiny, ssmall, smedium, sbig, sint, txt, utiny, usmall, umedium, ubig, uint, blb, nil)
        VALUES ".implode(",", $a)."
    ");
    if (!$re) {
        print "error: ".__FUNCTION__.": (".mysql_errno().") ".mysql_error()."\n";
        return 1;
    }

    return 0;
}

function run($test_a) {
    foreach ($test_a as $i => $test) {
        $test['ext'] = isset($test['ext']) ? $test['ext'] : '';
        $v = select_mylua($test['code'], $test['arg'], $test['ext']);
        if (count($v) >= 2) {
            print "\n\ncode:\n";
            var_export($test['code']);
            print "\n\narg:\n";
            var_export($test['arg']);
            print "\n\nvalue:\n";
            var_export($v);
            print "\n\ntest failed. returned >= 2 rows.\n";
        }
        $v = $v[0];
        if ($v !== $test['expect']) {
            print "\n\ncode:\n";
            var_export($test['code']);
            print "\n\narg:\n";
            var_export($test['arg']);
            print "\n\nresult\n";
            var_export($v);
            print "\n\nexpect\n";
            var_export($test['expect']);
            print "\n\ntest failed.\n";
            print "\n";
            return 1;
        } else {
            $line = $test['line'];
            $code = summary($test['code']);
            $arg  = summary($test['arg']);
            $ret  = summary($v);
            $exp  = summary($test['expect']);
            printf("%4d: ok. line=%-4s code=%-10s arg=%-10s %10s === %-10s", $i, $line, $code, $arg, $ret, $exp);
            if (isset($v['message'])) {
                print " ".$v['message'];
            } else if ($v) {
                print " ";
            } else {
                print " mysql_error: (".mysql_errno().") ".mysql_error();
            }
            print "\n";
        }
    }
    return 0;
}

function test($code, $arg, $json, $errno = 0, $error = "") {
    $trace = debug_backtrace();
    return array('line' => $trace[0]['line'], 'code' => $code, 'arg' => $arg, 'expect' => array('json' => $json, 'errno' => $errno, 'error' => $error));
}

function test_ext($code, $arg, $ext, $json, $errno = 0, $error = "") {
    $trace = debug_backtrace();
    return array('line' => $trace[0]['line'], 'code' => $code, 'arg' => $arg, 'ext' => $ext, 'expect' => array('json' => $json, 'errno' => $errno, 'error' => $error));
}

function error($message) {
    return array('ok' => false, 'message' => $message);
}

function ok($data = null) {
    $r = array('ok' => true);
    if (isset($data)) $r['data'] = $data;
    return $r;
}

function summary($v, $n = 10) {
    $s = var_export($v, 1);
    $s = implode(" ", explode("\n", $s));
    preg_match("/^.{0,$n}/u", $s, $m);
    return $m[0];
}

function select_mylua($code, $arg, $ext) {
    $re = mysql_query("SELECT mylua($code, $arg) as json $ext");
    $errno = mysql_errno();
    $error = mysql_error();
    if ($re) {
        $a = array();
        while ($row = mysql_fetch_assoc($re)) {
            $row["json"] = json_decode($row["json"], 1);
            $row["errno"] = $errno;
            $row["error"] = $error;
            $a[] = $row;
        }
        return $a;
    } else {
        return array(array("json" => null, "errno" => $errno, "error" => $error));
    }
}

function q($s) {
    return "'".mysql_real_escape_string($s)."'";
}
