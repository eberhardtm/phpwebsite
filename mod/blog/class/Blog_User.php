<?php

class Blog_User {

  function show(){
    $key = "front blog page";
    
    if ($content = PHPWS_Cache::get($key))
      return $content;
    
    $limit = 5;

    $db = & new PHPWS_DB("blog_entries");
    $db->setLimit($limit);
    $db->addOrder("date desc");
    $result = $db->getObjects("Blog");
    
    foreach ($result as $blog)
      $list[] = $blog->view(); 

    $content = implode("", $list);
    PHPWS_Cache::save($key, $content);
    return $content;

  }

}

?>