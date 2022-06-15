<?php

namespace Wanphp\Libray\Weixin\User;


use Wanphp\Libray\Mysql\BaseRepository;
use Wanphp\Libray\Mysql\Database;

class PublicRepository extends BaseRepository implements PublicInterface
{
  public function __construct(Database $database)
  {
    parent::__construct($database, self::TABLE_NAME, PublicEntity::class);
  }
}
