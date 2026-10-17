<?php
namespace fremeo\core\tests;

use fremeo\core\Security;
use PHPUnit\Framework\TestCase;

class SecurityTest extends TestCase {
    public function testResolveSecureDataPreservesRawActionAndRouteData(): void {
        $_SESSION = [];
        $security = new Security();
        $hash = $security->registerSecurityD('D[ALBUM][D][id-1][Title]');

        $base = [
            'ACTION' => 'save',
            'R' => [
                'ModuleId' => 'fremeo/gallery',
                'Page' => 'admin__gallery.edit',
                'Id' => 'id-1',
            ],
        ];

        $resolved = $security->resolveSecureData([$hash => 'Hello'], $base);

        $this->assertSame('save', $resolved['ACTION']);
        $this->assertSame('fremeo/gallery', $resolved['R']['ModuleId']);
        $this->assertSame('Hello', $resolved['ALBUM']['D']['id-1']['Title']);
    }

    public function testSecurePasswordInRouteSurvivesBootstrapping(): void {
        $_SESSION = [];
        $security = new Security();
        $hash = $security->registerSecurityD('R[Passwort]');

        $rawR = [
            'Id' => 'album-99',
            'Page' => 'frontend__gallery',
            'ModuleId' => 'fremeo/gallery',
        ];

        $D = ['R' => $rawR];
        $D = $security->resolveSecureData([$hash => 'correct-password'], $D);
        $R = [];
        $R = array_replace_recursive($R, $rawR);
        $R = array_replace_recursive($R, $D['R'] ?? []);
        $D['R'] = &$R;

        $this->assertSame('correct-password', $D['R']['Passwort']);
        $this->assertSame('album-99', $D['R']['Id']);
        $this->assertSame('frontend__gallery', $D['R']['Page']);
    }

    public function testSecurePasswordStillWorksWhenRequestContainsNoRawRArray(): void {
        $_SESSION = [];
        $security = new Security();
        $hash = $security->registerSecurityD('R[Passwort]');

        $D = [];
        $R = null;
        $D = $security->resolveSecureData([$hash => 'aa'], $D);
        $R = is_array($R) ? $R : [];
        $R = array_replace_recursive($R, $D['R'] ?? []);
        $D['R'] = &$R;

        $this->assertSame('aa', $D['R']['Passwort']);
        $this->assertSame('aa', $R['Passwort']);
    }
}
