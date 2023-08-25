<?php
class ViewsAPI extends API
{
	/**
	 * 从kangle服务器更新
	 */
	public function viewsUpdate()
	{
		return true;
	}

	public function viewDel($name)
	{
		if (daocall('views', 'viewsDel', array($name))) {
			if (apicall('dnssync', 'syncAllInit', array())) {
				return true;
			}
		}

		return false;
	}
}

?>