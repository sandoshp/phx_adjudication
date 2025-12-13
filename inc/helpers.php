<?php
function json_response($data,$status=200){ http_response_code($status); header('Content-Type: application/json'); echo json_encode($data); exit; }
function followup_end($date){ $dt=new DateTime($date); $dt->modify('+3 months'); return $dt->format('Y-m-d'); }
