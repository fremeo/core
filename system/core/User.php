<?php
namespace papp\phpapp;

class User {

    private \papp\CData $DB;

    public function __construct(\papp\CData $DB) {
        $this->DB = $DB;
    }

    /**
     * MASTER-FUNKTION
     * Erwartet ein einheitliches Datenarray:
     *
     * [
     *   'USER' => [
     *       'D' => [
     *           'UserId' => [
     *               'Active' => 1,
     *               'Name'   => '...',
     *               'Mail'   => '...',
     *               'Password' => '...',
     *               'ACCOUNT' => [
     *                   'AccId' => ['Active'=>1]
     *               ],
     *               'GROUP' => [
     *                   'GroupId' => ['Active'=>1]
     *               ]
     *           ],
     *           'UserId2' => '__DELETE__'
     *       ]
     *   ],
     *   'ACCOUNT' => [
     *       'D' => [
     *           'AccId' => [
     *               'Active'=>1,
     *               'Name'=>'...'
     *           ]
     *       ]
     *   ]
     * ]
     */
    public function setUser(array $d): bool {
        $this->DB->set_object($d);
        return true;
    }


    /**
     * MASTER-LESEN
     */
    public function getUser(array $filter): array|null {

        $F['USER']['W'] = [];

        if (!empty($filter['Id'])) {
            $F['USER']['W'][0]['ID'] = is_array($filter['Id']) ? $filter['Id'] : [$filter['Id']];
        }
        if (!empty($filter['Name'])) {
            $F['USER']['W'][0]['Name'] = $filter['Name'];
        }
        if (!empty($filter['Mail'])) {
            $F['USER']['W'][0]['Mail'] = $filter['Mail'];
        }
        if (!empty($filter['AccountId'])) {
            $F['USER']['W'][0]['ACCOUNT']['ID'] = $filter['AccountId'];
        }
        if (!empty($filter['GroupId'])) {
            $F['USER']['W'][0]['GROUP']['ID'] = $filter['GroupId'];
        }

        $this->DB->get_object($D, $F);
        return $D ?: null;
    }


    // ---------------------------------------------------------
    // ABGESPLITTETE FUNKTIONEN
    // ---------------------------------------------------------

    /**
     * User anlegen
     */
    public function createUser(string $name, string $mail, string $password, ?string $accountId = null, ?array $groups = null): bool {

        $userId = hash("crc32b", $mail . microtime(true));

        // Passwort hashen
        $hashedPw = password_hash($password, PASSWORD_DEFAULT);

        $D = [
            'USER' => ['D' => []],
            'ACCOUNT' => ['D' => []]
        ];

        // Wenn kein Account → neuen erstellen
        if ($accountId === null) {
            $accountId = hash("crc32b", $mail . rand());

            $D['ACCOUNT']['D'][$accountId] = [
                'Active' => 0,
                'Name'   => $name
            ];

            $active = 0;
        } else {
            $active = 1;
        }

        // USER
        $D['USER']['D'][$userId] = [
            'Active'   => $active,
            'Name'     => $name,
            'Mail'     => $mail,
            'Password' => $hashedPw,
            'ACCOUNT'  => [
                $accountId => ['Active'=>1]
            ]
        ];

        // Gruppen
        if (!empty($groups)) {
            foreach ($groups as $gid) {
                $D['USER']['D'][$userId]['GROUP'][$gid] = ['Active'=>1];
            }
        }

        return $this->setUser($D);
    }


    /**
     * User aktualisieren
     */
    public function updateUser(string $userId, array $fields): bool {

        $D['USER']['D'][$userId] = [];

        if (isset($fields['Name']))     $D['USER']['D'][$userId]['Name'] = $fields['Name'];
        if (isset($fields['Mail']))     $D['USER']['D'][$userId]['Mail'] = $fields['Mail'];
        if (isset($fields['Active']))   $D['USER']['D'][$userId]['Active'] = $fields['Active'];

        if (isset($fields['Password'])) {
            $D['USER']['D'][$userId]['Password'] =
                password_hash($fields['Password'], PASSWORD_DEFAULT);
        }

        if (isset($fields['AccountId'])) {
            $D['USER']['D'][$userId]['ACCOUNT'][$fields['AccountId']] = ['Active'=>1];
        }

        if (isset($fields['GroupId'])) {
            foreach ($fields['GroupId'] as $gid) {
                $D['USER']['D'][$userId]['GROUP'][$gid] = ['Active'=>1];
            }
        }

        return $this->setUser($D);
    }


    /**
     * User löschen
     */
    public function deleteUser(string|array $ids): bool {

        $ids = is_array($ids) ? $ids : [$ids];

        $D['USER']['D'] = [];

        foreach ($ids as $id) {
            $D['USER']['D'][$id] = '__DELETE__';
        }

        return $this->setUser($D);
    }


    /**
     * User einer Gruppe zuordnen
     */
    public function assignGroup(string $userId, string $groupId): bool {

        $D['USER']['D'][$userId]['GROUP'][$groupId] = ['Active'=>1];

        return $this->setUser($D);
    }


    /**
     * User einem Account zuordnen
     */
    public function assignAccount(string $userId, string $accountId): bool {

        $D['USER']['D'][$userId]['ACCOUNT'][$accountId] = ['Active'=>1];

        return $this->setUser($D);
    }
}
