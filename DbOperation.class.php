<?php
/**
 *@author JBL
 *@version 1.1
 *@Created: 2013-10-05
 *@Updated: 2013-12-05
 *Update: 1. Remove when import sql sql file which exclude comments '-' to solve sql in single or double quotation marks can not be imported
 *Sql 2. executed directly after reading a single line, to avoid re-combine sql statement to import sql array and then read from the array, improve efficiency

 * Note: sub-volume file is _v1.sql ending (xyz_v1.sql)
 * Function: mysql database backup volumes, choose to back up the table to achieve a single sql sql file and import volumes
 * Usage:
 *

 * ------1 Database backup (export) ------------------------------------ ------------------------
// Are the host, username, password, database name, database coding

$db = new DBManage ('localhost', 'root', 'root', 'test', 'utf8');
// Parameters: Backup which table (optional), backup directory (optional, defaults to backup), volume size (optional, default 2000, namely 2M)

$db->backup ();

 * ------2 Database Recovery (import) ------------------------------------ ------------------------
// Are the host, username, password, database name, database coding

$db = new DBManage ('localhost', 'root', 'root', 'test', 'utf8');
// Parameters: sql file
$db->restore ('./backup_dump/xyz_all_v1.sql');

 * ------------------------------------------------- ---------------------
*/

class DbOperation {
    var $db; // database connection
    var $database; // used in database
    var $sqldir; // database backup folder
    // Newline
    private $ds = "\ n";
    // Variable to store SQL
    public $sqlContent = "";
    // Sql statement at the end of each character
    public $sqlEnd = ";";

    /**
     * Initialization
     *
     * @param string $host
     * @param string $username
     * @param string $password
     * @param string $database
     * @param string $charset
     */
    function __construct($host = 'localhost', $username = 'root', $password = '', $database = 'test', $charset = 'utf8') {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->database = $database;
        $this->charset = $charset;
        set_time_limit (0); // no time limits
	ob_end_flush ();

        // Connect to the database
        $this->db = @mysql_connect ( $this->host, $this->username, $this->password ) or die( '<p class="dbDebug"><span class="err">Mysql Connect Error : </span>'.mysql_error().'</p>');

        // Select which database
        mysql_select_db ( $this->database, $this->db ) or die('<p class="dbDebug"><span class="err">Mysql Connect Error:</span>'.mysql_error().'</p>');

        // Database encoding
        mysql_query ( 'SET NAMES ' . $this->charset, $this->db );

    }

    /*
     * Added query the database table
    */
    function getTables () {
        $res = mysql_query ("SHOW TABLES");
        $tables = array ();
        while ($row = mysql_fetch_array ($res)) {
            $tables [] = $row [0];
        }
        return $tables;
    }

    /*
     *
     * ------------------------------------------ Database backup start ---- -------------------------------------------------- ----
    */

    /**
     * Database Backup
     * Parameters: Backup which table (optional), backup directory (optional, defaults to backup), volume size (optional, defaults to 2000, namely 2M)
     *
     * @param $string $dir
     * @param int $size
     * @param $string $tablename
    */
    function backup ($tablename = '', $dir, $size) {
        $dir = $dir ? $dir : './backup/';
        // Create a directory
        if (! is_dir ($dir)) {
            mkdir ($dir, 0777, true) or die ('Create Folder failed');
        }
        $size = $size ? $size : 2048;
        $sql =    '';
        // Only backup a table
        if (! empty ($tablename)) {
            if(@mysql_num_rows(mysql_query("SHOW TABLES LIKE '".$tablename."'")) == 1) {
             } else {
                $this->_showMsg('Table - <b>' . $tablename . '</ b> - does not exist, please check!', True);
                die ();
            }
            $this->_showMsg('being backed table <span class = "imp">'. $tablename. '</ span>');
            // Insert dump information
            $sql =     $this->_retrieve();
            // Insert Table structure information
            $sql = $this->_insert_table_structure ($tablename);
            // Insert data
            $data = mysql_query( "select * from ". $tablename );
            // Filename in the previous section
            $filename = date( 'YmdHis' ) . "_".$tablename;
            // Number of fields
            $num_fields = mysql_num_fields ( $data );
            // The first volume fraction
            $p = 1;
            // Loop for each record
            while ($record = mysql_fetch_array ($data)) {
                // Single record
                $sql =  $this->_insert_record ($tablename, $num_fields, $record);
                // If greater than the volume size, then write to the file
                if (strlen ( $sql ) >= $size * 1024) {
                    $file = $filename . "_v" . $p . ".sql";
                    if ($this->_write_file ( $sql, $file, $dir )) {
                        $this-> _showMsg("table - <b>". $tablename ."</b> - volume - <b>".$P. "</b> -. Data backup is complete, the backup file [<span class = 'imp'> </span>] ").$dir. $file;
                    } else {
                        $this->_showMsg("Backup table - <b>". $tablename ."< b> - fail", true);
                        return false;
                    }
                    // Next volumes
                    $p ++;
                    // Reset the $sql variable is empty, recalculate the variable size
                    $sql =  "";
                }
            }
            // Clear the data in a timely manner
            unset ($data, $record);
            // Sql size is not enough volume size
            if ($sql != "") {
                $filename .= "_v". $p .".sql";
                if ($this->_write_file ( $sql, $filename, $dir )) {
                    $this->_showMsg("table - <b>". $tablename. "</b> - volume - <b>".$P ."</ b> -. Data backup is complete, the backup file [<span class = 'imp'> </ span>] "). $dir. $filename;
                } else {
                    $this->_showMsg("backup volumes - <b>". $p ."</ b> - fail <br />");
                    return false;
                }
            }
            $this->_showMsg("! Congratulations on your <span class = 'imp'> Backup success </ span>");
        } else {
            $this->_showMsg('being backed');
            // Backup all tables
            if ($tables = mysql_query ("show table status from".$this->database)) {
                $this->_showMsg("reads the database structure success!");
            } else {
                $this->_showMsg("reads the database structure failed!");
                exit (0);
            }
            // Insert dump information
            $sql =  $this-> _retrieve ();
            // Filename in the previous section
            $filename = date( 'YmdHis' ) . "_all";
            // Find out all the tables
            $tables = mysql_query ( 'SHOW TABLES' );
            // The first volume fraction
            $p = 1;
            // Loop through all tables
            while ($table = mysql_fetch_array ($tables)) {
                // Get the table name
                $Tablename = $table [0];
                // Get the table structure
                $sql =  $this-> _insert_table_structure($tablename);
                $data = mysql_query( "select * from ".$tablename );
                $num_fields = mysql_num_fields ( $data );

                // Loop for each record
                while ($record = mysql_fetch_array ($data)) {
                    // Single record
                    $sql =  $this->_insert_record($tablename, $num_fields, $record);
                    // If greater than the volume size, then write to the file
                    if (strlen ($sql) >= $size * 1000) {

                        $file = $filename . "_v" .$p . ".sql";
                        // Write the file
                        if ($this->_write_file($sql, $file, $dir)) {
                           $this-> _showMsg ("- Volume - <b>". $p ."</b> - Data backup is complete, the backup file [<span class = 'imp'>". $dir. $file. "</span.. >] ");
                        } else {
                           $this -> _showMsg ("roll - <b>". $p. "</b> - backup failed!", true);
                            return false;
                        }
                        // Next volumes
                        $p ++;
                        // Reset the $sql variable is empty, recalculate the variable size
                        $sql =  "";
                    }
                }
            }
            // Sql size is not enough volume size
            if ($sql != "") {
                $filename .= "_v". $p . ".sql";
                if ($this->_write_file ($sql, $filename, $dir)) {
                   $this -> _showMsg ("- Volume - <b>". $p ."</b> - Data backup is complete, the backup file [<span class = 'imp'>". $dir .$filename ."</ span.. >] ");
                } else {
                    $this->_showMsg("roll - <b>". $p ."</b> - backup failed", true);
                    return false;
                }
            }
            $this->_showMsg("! Congratulations on your <span class = 'imp'> Backup success </ span>");
        }
    }

    // Output information in a timely manner
    private function _showMsg ($msg, $err = false) {
        $err = $err ? "<span class='err'>ERROR:</span>" : '' ;
         echo "<p class = 'dbDebug'>". $err. $msg. "</ p>";
        flush ();

    }

    /**
     * Insert the basic information database backup
     *
     * @return string
    */
    private function _retrieve () {
        $value = '';
        $value .= '-'. $this->ds;
        $value .= '- MySQL database dump'. $this->ds;
        $value .= '- Created by DbManage class, Power By JBL.'. $this->ds;
        $value .= '- http://jblsolutions.net'. $this->ds;
        $value .= '-' .$this->ds;
        $value .= '- Host:'. $this->host. $this->ds;
        $value .= '- to generate the date:.......'.date('Y') .'in'. date('m'). 'month'. date('d') .'date'. date('H:i'). $this->ds;
        $value .= '- MySQL version:'. mysql_get_server_info(). $this->ds;
        $value .= '- PHP version:'. phpversion(). $this->ds;
        $value .= $this->ds;
        $value .= '-'. $this->ds;
        $value .= '- Database: `'. $this->database. '`' .$this->ds;
        $value .= '-' .$this->ds. $this->ds;
        $value .= '- ------------------------------------------- ------------ ';
        $value .= $this->ds .$this->ds;
        return $value;
    }

    /**
     * Insert Table Structure
     *
     * @param unknown_type $table
     * @return string
     */
    private function _insert_table_structure ($table) {
        $sql =  '';
        $sql .= "-". $this->ds;
        $sql .= "- the structure of the table" .$table. $this->ds;
        $sql .= "-" .$this->ds. $this->ds;

        // If there is to delete the table
        $sql .= "DROP TABLE IF EXISTS` ".$table.'`' .$this->sqlEnd. $this->ds;
        // Get detailed table information
        $res = mysql_query ( 'SHOW CREATE TABLE `' . $table . '`' );
        $row = mysql_fetch_array ( $res );
        $sql .= $row [1];
        $sql .= $this->sqlEnd . $this->ds;
        // Add
        $sql .= $this->ds;
        $sql .= "--" . $this->ds;
        $sql .= "-- dump the data in the table". $table.$this->ds;
        $sql .= "--" . $this->ds;
        $sql .= $this->ds;
        return $sql;
    }

    /**
     * Insert a single record
     *
     * @param string $table
     * @param int $num_fields
     * @param array $record
     * @return string
    */
    private function _insert_record ($table, $num_fields, $record) {
        // Sql field separated by commas
        $insert = '';
        $comma = "";
        $insert .= "INSERT INTO `" . $table . "` VALUES(";
        // Loop following the contents of each sub-segment
        for($i = 0; $i < $num_fields; $i ++) {
            $insert .= ($comma . "'" . mysql_real_escape_string ( $record [$i] ) . "'");
            $comma = ",";
        }
        $insert .= ");" . $this->ds;
        return $insert;
    }

    /**
     * Written to the file
     *
     * @param string $sql
     * @param string $filename
     * @param string $dir
     * @return boolean
    */
    private function _write_file ($sql, $filename, $dir) {
        $dir = $dir ? $dir: './backup_dump/';
        // Create a directory
        if (! is_dir ($dir)) {
            mkdir ($dir, 0777, true);
        }
        $re = true;
        if (! @$fp = fopen ( $dir . $filename, "w+" )) {
            $re = false;
            $this->_showMsg("open sql file failed!", True);
        }
        if (! @fwrite ( $fp, $sql )) {
            $re = false;
            $this->_showMsg("write sql file fails, the file is writable", true);
        }
        if (! @fclose ( $fp )) {
            $re = false;
            $this->_showMsg("close sql file failed!", True);
        }
        return $re;
    }

    /*
     *
     * ------------------------------- On: Database Export ----------- dividing line - --------- By: database import --------------------------------
    */

    /**
     * Import backup data
     * Note: sub-volume file format xyz_all_v1.sql
     * Parameters: file path (required)
     *
     * @param string $sqlfile
    */
    function restore ($sqlfile) {
        // Check if a file exists
        if (! file_exists ($sqlfile)) {
            $this->_showMsg("sql file does not exist, check!", True);
            exit ();
        }
        $this->lock ($this->database);
        // Get the database storage location
        $sqlpath = pathinfo ($sqlfile);
        $this->sqldir = $sqlpath ['dirname'];
        // Check whether to include sub-volume, will be similar to xyz_all_v1.sql separated from _v, there is illustrated volume partakers
        $volume = explode ( "_v", $sqlfile );
        $volume_path = $volume [0];
        $this->_showMsg("Do not close your browser and refresh to prevent the program is terminated, if inadvertently damaged database structure will lead to!");
        $this->_showMsg("Importing backup data, please wait!");
        if (empty ($volume [1])) {
            $this->_showMsg("Importing sql: <span class = 'imp'>" .$sqlfile .'</span>');
            // No volumes
            if ($this->_import ( $sqlfile )) {
                $this->_showMsg("database import was successful!");
            } else {
                 $this->_showMsg('database import fails!', True);
                exit ();
            }
        } else {
            // Volumes exist, it is the first sort of get the current volume, the loop executes the remaining volumes
            $volume_id = explode ( ".sq", $volume [1] );
            // Current volumes for $volume_id
            $volume_id = intval ( $volume_id [0] );
            while ($volume_id) {
                $tmpfile = $volume_path . "_v" . $volume_id . ".sql";
                // There are other volumes, continue
                if (file_exists ($tmpfile)) {
                    // Perform the import method
                    $this->msg = "sub-volume being imported .$volume_id. <span style = 'color: # f00;'>". $tmpfile .'</ span> <br/>';
                    if ($this->_import ($tmpfile)) {

                    } else {
                        $volume_id = $volume_id ? $volume_id :1;
                        exit ("Import volumes: <span style = 'color: # f00;'>".$tmpfile .'! </span> failed database structure may be corrupted, try to start the import volumes from 1!');
                    }
                } else {
                    $this->msg = "This sub-volume backup all imported successfully <br />!";
                    return;
                }
                $volume_id ++;
            }
        }if (empty ( $volume [1] )) {
            $this->_showMsg("Importing sql: <span class = 'imp'>" .$sqlfile. '</ span>');
            // No volumes
            if ($this->_import ($sqlfile)) {
                $this->_showMsg("database import was successful!");
            } else {
                 $this->_showMsg('database import fails!', True);
                exit ();
            }
        } else {
            // Volumes exist, it is the first sort of get the current volume, the loop executes the remaining volumes
            $volume_id = explode ( ".sq", $volume [1] );
            // Current volumes for $volume_id
            $volume_id = intval ( $volume_id [0] );
            while ($volume_id) {
                $tmpfile = $volume_path . "_v" . $volume_id . ".sql";
                // There are other volumes, continue
                if (file_exists ($tmpfile)) {
                    // Perform the import method
                    $this->msg = "sub-volume being imported. $volume_id. <span style = 'color: # f00;'>". $tmpfile .'</span> <br/>';
                    if ($this->_import ($tmpfile)) {

                    } else {
                        $volume_id = $volume_id ? $volume_id :1;
                        exit ("Import volumes: <span style = 'color: # f00;'>".$tmpfile .'! </ span> failed database structure may be corrupted, try to start the import volumes from 1!');
                    }
                } else {
                    $this->msg = "This sub-volume backup all imported successfully <br />!";
                    return;
                }
                $volume_id ++;
            }
        }
    }

    /**
     * The sql into the database (ordinary import)
     *
     * @param string $sqlfile
     * @return boolean
    */
    private function _import ($sqlfile) {
        // sql file that contains an array of
        $sqls = array ();
        $f = fopen ( $sqlfile, "rb" );
        // Create a table buffer variable
        $create_table = '';
        while (! feof ($f)) {
            // Read each line of sql
            $line = fgets ( $f );
            // This step in order to create a synthetic full table sql statement
            // If the end does not contain ';' (that is, a complete sql statement, here is the insert statements), and does not contain 'ENGINE =' (that is, create the last sentence of the table)
            if (! preg_match ( '/;/', $line ) || preg_match ( '/ENGINE=/', $line )) {
                // The sql statement will create a table with sql connection kept up
                $create_table .= $line;
                // If you create a table that contains the last one
                if (preg_match ( '/ENGINE=/', $create_table)) {
                    // Execute sql statements to create the table
                    $this->_insert_into($create_table);
                    // Clear the current, ready to create the next table
                    $create_table = '';
                }
                // Skip this
                continue;
            }
            // Execute sql statement
            $this->_insert_into($line);
        }
        fclose ( $f );
        return true;
    }

    // Insert a single sql statement
    private function _insert_into($sql){
        if (! mysql_query ( trim ( $sql ) )) {
            $this->msg .= mysql_error ();
            return false;
        }
    }

    /*
     * Database Import end --------------- ------------------------------- ------------------
    */

    // Close the database connection
    private function close() {
        mysql_close ( $this->db );
    }

    // lock the database, in order to avoid the time backup or import
    private function lock($tablename, $op = "WRITE") {
        if (mysql_query ( "lock tables " . $tablename . " " . $op ))
            return true;
        else
            return false;
    }

    // Unlock
    private function unlock () {
        if (mysql_query ("unlock tables"))
            return true;
        else
            return false;
    }

    // Destructor
    function __destruct () {
        if ($this->db) {
            mysql_query ("unlock tables", $this->db);
            mysql_close ($this->db);
        }
    }

}
