<?php
/**
 * TEST ONLY: fake external sources and capture outgoing mail.
 * Used by wp-smoke.php, and as mu-plugin for manual browser tests without internet.
 */
add_filter('pre_http_request', function ($pre, $args, $url) {
  if ($pre !== false) return $pre; // a test already supplied an answer
  $ok = fn($body) => ['headers'=>[], 'body'=>$body, 'response'=>['code'=>200,'message'=>'OK'], 'cookies'=>[], 'filename'=>null];
  if (str_contains($url, 'gov.uk/api/content/foreign-travel-advice/saudi-arabia')) return $ok(json_encode(['description'=>'Saudi','public_updated_at'=>'2026-09-20T08:00:00Z','details'=>['alert_status'=>['avoid_all_but_essential_travel_to_parts'],'parts'=>[['slug'=>'warnings-and-insurance','body'=>'<p>FCDO advises against all but essential travel to within 10km of the border with Yemen.</p>']]]]));
  if (str_contains($url, 'gov.uk/api/content/foreign-travel-advice/norway')) return $ok(json_encode(['description'=>'Norway','public_updated_at'=>'2026-08-01T08:00:00Z','details'=>['alert_status'=>[],'parts'=>[['slug'=>'warnings-and-insurance','body'=>'<p>No specific warnings.</p>']]]]));
  if (str_contains($url, 'gov.uk/api/content/foreign-travel-advice/iraq')) return $ok(json_encode(['description'=>'Iraq','public_updated_at'=>'2026-09-22T08:00:00Z','details'=>['alert_status'=>['avoid_all_travel_to_whole_country'],'parts'=>[['slug'=>'warnings-and-insurance','body'=>'<p>FCDO advises against all travel to Iraq.</p>']]]]));
  if (preg_match('#auswaertiges-amt.de/opendata/travelwarning$#', $url)) return $ok(json_encode(['response'=>['1'=>['iso3CountryCode'=>'SAU'],'2'=>['iso3CountryCode'=>'NOR'],'3'=>['iso3CountryCode'=>'IRQ']]]));
  if (preg_match('#travelwarning/(\d)$#', $url, $m)) { $f=['1'=>['partialWarning'=>true],'2'=>[],'3'=>['warning'=>true]][$m[1]]; return $ok(json_encode(['response'=>[$m[1]=>$f+['lastModified'=>1758000000000,'content'=>'<p>Deutscher Hinweis.</p>']]])); }
  if (str_contains($url, 'nederlandwereldwijd')) return $ok('<d><introduction><![CDATA[<p>Voor het grootste deel van het land geldt kleurcode geel. Voor de strook langs de grens geldt kleurcode rood.</p>]]></introduction><canonical>https://www.nederlandwereldwijd.nl/reisadvies/x</canonical><lastmodified>2026-09-15T00:00:00Z</lastmodified></d>');
  if (str_contains($url, 'gdeltproject')) return $ok(json_encode(['articles'=>[['title'=>'Drone intercepted over eastern province','url'=>'https://example.com/1','domain'=>'example.com','seendate'=>gmdate('Ymd\THis\Z', time()-7200)],['title'=>'Protest reported in capital','url'=>'https://example.com/2','domain'=>'news.example','seendate'=>gmdate('Ymd\THis\Z', time()-20000)]]]));
  return $pre;
}, 10, 3);
add_filter('pre_wp_mail', function ($null, $atts) { file_put_contents(WP_CONTENT_DIR.'/last-mail.txt', $atts['to']."\n".$atts['subject']."\n".$atts['message']); return true; }, 10, 2);
