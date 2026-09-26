<?php
namespace app\services;

use Yii;
use RuntimeException;
use yii\db\Connection;

final class DatabaseBackupService
{
    private const MAGIC = '-- CATATAN_KEUANGAN_SQL_BACKUP_V1';
    private const MAX_UPLOAD = 268435456; // 256 MiB

    public static function create(string $targetFile): array
    {
        $db=Yii::$app->db;
        $pdo=$db->pdo;
        $tables=self::baseTables($db);
        $fh=@fopen($targetFile,'wb');
        if(!$fh)throw new RuntimeException('Tidak dapat membuat file SQL backup.');
        $rows=0;
        try{
            fwrite($fh,self::MAGIC."\n");
            fwrite($fh,'-- Created at: '.date(DATE_ATOM)."\n");
            fwrite($fh,"SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
            foreach($tables as $table){
                $quoted=$db->quoteTableName($table);
                $create=$db->createCommand('SHOW CREATE TABLE '.$quoted)->queryOne();
                if(!$create)continue;
                $ddl=(string)(array_values($create)[1]??'');
                if($ddl==='')continue;
                fwrite($fh,"-- Table: ".$table."\nDROP TABLE IF EXISTS ".$quoted.";\n".$ddl.";\n\n");
                $cmd=$db->createCommand('SELECT * FROM '.$quoted);
                $reader=$cmd->query();
                while(($row=$reader->read())!==false){
                    $cols=[];$vals=[];
                    foreach($row as $col=>$value){$cols[]=$db->quoteColumnName((string)$col);$vals[]=self::sqlValue($pdo,$value);}
                    fwrite($fh,'INSERT INTO '.$quoted.' ('.implode(',',$cols).') VALUES ('.implode(',',$vals).');'."\n");
                    $rows++;
                }
                $reader->close();
                fwrite($fh,"\n");
            }
            fwrite($fh,"SET FOREIGN_KEY_CHECKS=1;\n");
        }finally{fclose($fh);}
        return ['tables'=>count($tables),'rows'=>$rows,'bytes'=>(int)@filesize($targetFile)];
    }

    public static function restore(string $sqlFile): array
    {
        if(!is_file($sqlFile))throw new RuntimeException('File SQL tidak ditemukan.');
        $size=(int)@filesize($sqlFile);if($size<=0||$size>self::MAX_UPLOAD)throw new RuntimeException('File SQL kosong atau melebihi 256 MB.');
        $fh=fopen($sqlFile,'rb');$first=trim((string)fgets($fh));fclose($fh);
        if($first!==self::MAGIC)throw new RuntimeException('Hanya SQL backup yang dibuat oleh Catatan Keuangan V45 yang dapat direstore dari panel ini.');
        $db=Yii::$app->db;
        @set_time_limit(0);
        $executed=0;
        foreach(self::statements($sqlFile) as $sql){
            $sql=trim($sql);if($sql===''||str_starts_with($sql,'--'))continue;
            $db->createCommand($sql)->execute();$executed++;
        }
        try{$db->schema->refresh();}catch(\Throwable $e){}
        return ['statements'=>$executed,'bytes'=>$size];
    }

    private static function baseTables(Connection $db): array
    {
        $rows=$db->createCommand('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'')->queryAll();
        $out=[];foreach($rows as $r){$v=array_values($r);if(isset($v[0]))$out[]=(string)$v[0];}sort($out,SORT_STRING);return $out;
    }
    private static function sqlValue(\PDO $pdo,$v): string
    {
        if($v===null)return 'NULL';
        if(is_bool($v))return $v?'1':'0';
        if(is_int($v)||is_float($v))return (string)$v;
        return $pdo->quote((string)$v);
    }
    private static function statements(string $file): \Generator
    {
        $fh=fopen($file,'rb');if(!$fh)throw new RuntimeException('File SQL tidak dapat dibaca.');
        $buf='';$quote=null;$escape=false;
        try{
            while(!feof($fh)){
                $chunk=fread($fh,65536);if($chunk===false)throw new RuntimeException('Gagal membaca SQL backup.');
                $len=strlen($chunk);
                for($i=0;$i<$len;$i++){
                    $c=$chunk[$i];$buf.=$c;
                    if($quote!==null){
                        if($escape){$escape=false;continue;}
                        if($c==='\\'){$escape=true;continue;}
                        if($c===$quote){$next=$i+1<$len?$chunk[$i+1]:null;if($next===$quote){$buf.=$next;$i++;continue;}$quote=null;}
                        continue;
                    }
                    if($c==="'"||$c==='"'||$c==='`'){$quote=$c;continue;}
                    if($c===';'){$sql=trim(substr($buf,0,-1));$buf='';if($sql!=='')yield self::stripLeadingComments($sql);}
                }
            }
            $tail=trim($buf);if($tail!=='')yield self::stripLeadingComments($tail);
        }finally{fclose($fh);}
    }
    private static function stripLeadingComments(string $sql): string
    {
        do{$before=$sql;$sql=preg_replace('/^\s*--[^\r\n]*(?:\r?\n|$)/','',$sql,1)??$sql;}while($sql!==$before);return trim($sql);
    }
}
