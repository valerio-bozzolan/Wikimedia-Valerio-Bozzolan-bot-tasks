<?php
// capture the output
ob_start();
?>
== {{int:filedesc}} ==
{{Information
|Description=profile image of <?php echo "$name $surname" ?>, volleyball player (<?php echo $nation ?>)
|Source=[http://www.legavolley.it/ricerca/?TipoOgg=ATL Lega Pallavolo Serie A]
|Date=<?php echo $date ?>
|Author=Lega Pallavolo Serie A
|Permission=
|other_versions=
}}
== {{int:license-header}} ==
{{Lega Pallavolo|2019}}

[[Category:2019 files from Legavolley stream]]
<?php
// return the output
$text = ob_get_contents();
ob_end_clean();
return $text;
?>
