<!DOCTYPE HTML>
<html lang = "en-US">
<head>
<meta charset = "UTF-8">
<title> DbManager </title>
    <style type = "text / css">
* {
    margin: 0;
    padding: 0;
}

body {
    font-size: 12px;
    font-family: 'Microsoft elegant black';
    color: #666;
}
.dbDebug {
}

.dbDebug .err {
    color: #f00;
    margin: 0 5px 0 0;

}
.dbDebug .ok {
    color: #06f;
    margin: 0 5px 0 0;

}
.dbDebug b {
    color: #06f;
    font-weight: normal;
}

.dbDebug .imp {
    color: #f06;
}
</style>
</head>
<body>
    <?php
    /**
     * Created by JBL.
     * User: root
     * Date: 2013-12-09
     */

require 'DbOperation.class.php';

// ------1 Database backup (export) ----------------------------------- -------------------------
// Are the host, username, password, database name, database coding
$Db = new DbOperation('localhost', 'root', 'root', 'dataservices', 'utf8');
// Parameters: Backup which table (optional), backup directory (optional, defaults to backup), volume size (optional, default 2048, namely 2M)
// $ Db-> backup ('', '', '');
$Db-> restore('./backup_dump/xyz_v1.sql');


?>

</body>
</html>
