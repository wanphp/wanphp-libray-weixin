<?php
/**
 * Created by PhpStorm.
 * User: 火子 QQ：284503866.
 * Date: 2020/8/29
 * Time: 17:09
 */

namespace Wanphp\Libray\Weixin\User;


use Wanphp\Libray\Mysql\BaseRepository;
use Wanphp\Libray\Mysql\Database;

class UserRepository extends BaseRepository implements UserInterface
{
  public function __construct(Database $database)
  {
    parent::__construct($database, self::TABLE_NAME, UserEntity::class);
  }

  public function getUser($id): array
  {
    return $this->db->select(UserInterface::TABLE_NAME . '(u)', [
      '[>]' . PublicInterface::TABLE_NAME . '(p)' => ["u.id" => "id"]
    ],
      ['u.nickname', 'u.headimgurl', 'u.name', 'u.tel', 'p.tagid_list[JSON]', 'p.parent_id'],
      ['u.id' => $id]
    ) ?: [];
  }

  public function getUsers($where): array
  {
    return $this->db->select(UserInterface::TABLE_NAME . '(u)', [
      '[>]' . PublicInterface::TABLE_NAME . '(p)' => ["u.id" => "id"]
    ],
      ['u.id', 'u.nickname', 'u.headimgurl', 'u.name', 'u.tel', 'p.openid', 'p.tagid_list[JSON]', 'p.subscribe', 'p.parent_id'],
      $where
    ) ?: [];
  }
}
