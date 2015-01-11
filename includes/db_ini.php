<?php
/**
 * Configuración y datos iniciales de la base de datos.
 *
 *
 * @author 	Qstomizer
 * @package 	Qstomizer/Includes
 * @version     1.0.0
 */

    $tabla = $prefijodb."qstomizer_plugin_nonces";
    $strSQL = "CREATE TABLE `$tabla` (
              `id` bigint(25) unsigned NOT NULL AUTO_INCREMENT,
              `key_link` varchar(255) COLLATE utf8_bin DEFAULT NULL,
              `id_customizable` bigint(20) DEFAULT NULL,
              `fecha` bigint(20) DEFAULT NULL,
              `url_solicitante` varchar(2000) COLLATE utf8_bin DEFAULT NULL,
              `post_aduplicar` int(11) DEFAULT NULL,
              `tipo_solicitud` int(11) DEFAULT NULL,
              `id_order` int(11) DEFAULT NULL,
              `url_imagen` varchar(2000) DEFAULT NULL,
              PRIMARY KEY (`id`))";
    dbDelta($strSQL);

    
?>
