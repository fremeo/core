<?php

namespace phpapp;

class phpapp {

	private string $DB;

    public function __construct(CData $DB) {
        $this->DB = $DB;
    }

	#--SEO-Link--Funktionen------------------------
	function get_linkid_by_seourl($SeoURL) {
		return hash("crc32b", $SeoURL); 
	}
	function set_seourl($Page, $ModuleId, $SeoURL, $Param=[]) {
		$hURL = $this->get_linkid_by_seourl($SeoURL);
		$D['LINK']['D'][$hURL] = [
							'Active'	=> 1,
							'FromURL'	=> $SeoURL,
							'ModuleId'	=> $ModuleId,
							'ToURL'		=> "R[Page]={$Page}&R[ModuleId]={$ModuleId}&".http_build_query($Param), #$Param
						];
		$this->DB->set_object($D); 
	}
	function unset_seourl_by_id(string|array $LinkId): void {
		$ids = is_array($linkId) ? $linkId : [$linkId];
		// Alle IDs in das Delete-Array eintragen 
		foreach ($ids as $id) { 
			$D['LINK']['D'][$id] = '__DELETE__'; 
		}
		$this->DB->set_object($D); 
	}
	function unset_seourl_by_seourl(string|array $SeoURL): void  {
		$urls = is_array($SeoURL) ? $SeoURL : [$SeoURL];
		foreach ($urls as $url) { 
			$hURL[] = $this->get_linkid_by_seourl($SeoURL);
		}
		$this->unset_seourl_by_id($hURL);
	}
	function get_seourl_by_id(string|array $LinkId): array|null {
		$F['LINK']['W'][0]['ID'] = [$LinkId];
		$this->DB->get_object($D,$F);
		return $D?:null;
	}
	function get_seourl_by_seourl(string $SeoURL): array|null {
		$hURL = $this->get_linkid_by_seourl($SeoURL); 
		$D = $this->get_seourl_by_id($hURL);
		return $D?:null;
	}

}