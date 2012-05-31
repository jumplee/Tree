<?php

/**
 * a better way to a tree ?
 * you can treat it as a abstract DAL(data access layer)
 * 
 * */
class Tree
{

	private $data = array();
	//分割符号
	private $spVar = '-';
	private $localpath = 'local_path';
	private $abspath = 'abs_path';
	private $pid = 'parent_id';
	private $content = 'name';
	private $dao = null;
	private $tableName = '';

	/**
	 * @param string $table_name 表的名字
	 * */
	function __construct($table_name)
	{
		$this->dao = M($table_name);
		$this->tableName = C('DB_PREFIX') . $table_name;
	}
	function outArrary()
	{
		$tree = $this->getTree();
		$len = count($tree);
		$tree[0]['cha'] =0 ;
		for ($k = 1; $k < $len; $k++) {
			$cha = substr_count($tree[$k][$this->abspath], $this->spVar) - substr_count($tree[$k - 1][$this->abspath], $this->spVar);
			$tree[$k]['cha']=$cha;
		}
		return $tree;
	}
	function _htmlPrint($tree)
	{
		$str = '';
		// set as default
		$ul = '<ul>';
		$ul_t = '</ul>';
		$li = '<li>';
		$li_t = '</li>';
		//
		//初始化是手动滴，没有“根目录”
		$str.=$ul;
		//you can change the line below and the $block in the for{} to costumise?
		//if((substr_count($tree[1][$this->abspath],$this->spVar)-substr_count($tree[$0][$this->abspath],$this->spVar))>0){}
		$str.=$li . '<a href="#" id="' . $tree[0]['id'] . '">' . $tree[0]['name'] . '</a>';
		$len = count($tree);
		for ($k = 1; $k < $len; $k++) {
			$cha = substr_count($tree[$k][$this->abspath], $this->spVar) - substr_count($tree[$k - 1][$this->abspath], $this->spVar);
			$block = '<a href="#" id="' . $tree[$k]['id'] . '">' . $tree[$k]['name'] . '</a>';
			$block2='<a href="'.$tree[$k]['id'].'">'.'&nbsp&nbsp'.$tree[$k]['name'].'</a>';
			if ($cha == 0) {
				$str.=$li_t;
				$str.=$li . $block;
			} else if ($cha > 0) {
				$str.=$ul;
				$str.=$li . $block;
			} else if ($cha < 0) {
				$str.=$li_t;
				// o..0!! 要用正数，所以加上负号
				$str.=str_repeat($ul_t . $li_t, -$cha);
				$str.=$li . $block;
			}
		}
		$str.=$li_t;
		$str.=$ul_t;
		return $str;
	}

	/**
	  public function Tree2Array($input)
	  {
	  $ar=array();
	  $i=0;
	  $count=1;
	  $len=count($tree);
	  for($k=1;$k<$len;$k++){
	  if($count==count(explode($this->spVar,$input[$k][$this->$abspath])))
	  {
	  $ar[$i]=$input[$k];
	  $i += 1;
	  }else{
	  if($count>count(explode($this->spVar,$input[$k][$this->$abspath]))){
	  $count=count(explode($this->spVar,$input[$k][$this->$abspath]));
	  $ar[$i]=$count;
	  $i += 1;
	  $ar[$i]=$input[$k];
	  }else{
	  $count += 1;
	  $ar[$i]=$count;
	  $i += 1;
	  $ar[$i]=$input[$k];
	  }
	  }
	  }
	  return $ar;
	  }
	 * */

	/**
	 * 获取直系的子节点；
	 * */
	public function getChild($id)
	{
		return $this->where($this->pid . '=' . $id)->select();
	}

	public function getParent($id)
	{
		$path = $this->dao->where('id=' . $id)->getField($this->localpath);
		$parents = explode($this->spVar, $path);
		array_shift($parents);
		$map = array();
		$map['id'] = array('in', $parents);
		return $this->dao->where($map)->select();
	}

	/**
	 * 没有参数得到完整的树
	 * @return array
	 *
	 * */
	public function getTree($id=0)
	{
		$tree = null;
		if (0 === $id) {
			$tree = $this->dao->field('*,concat(local_path,\'-\',id) as abs_path')->order($this->abspath)->select();
			return $tree;
		} else {
			$absArr = $this->dao->where('id=' . $id)->getField($this->localpath);
			$abspath = $absArr[0] . '-' . $id;
			$tree = $this->dao->field('*, concat(local_path,\'-\',id) as abs_path')->where($this->localpath . ' like \'' . $abspath . '%\'')->order($this->abspath)->select();
		}
		return $tree;
	}

	/**
	 * 	获取所有子节点的id
	 * @param <type> $id
	 * @return <type> array;
	 */
	public function getCId($id=0)
	{
		$tt = $this->getTree($id);
		$ids = array();
		foreach ($tt as $child) {
			$ids[] = $child['id'];
		}
		return $ids;
	}

	function htmlPrintAll()
	{
		return $this->_htmlPrint($this->getTree());
	}

	function allAsSelect($name='pid')
	{
		return $this->_printSelect($this->getTree(), $name);
	}

	function addNode($pid, $content)
	{
		if (0 == $pid) {
			$data[$this->content] = $content;
			$data[$this->pid] = $pid;
			$data[$this->localpath] = '0';
			return $this->dao->add($data);
		} else {
			$absArr = $this->dao->field($this->localpath)->where('id=' . $pid)->select();
			if ($absArr) {
				$abs = $absArr[0][$this->localpath] . '-' . $pid;
				$data[$this->content] = $content;
				$data[$this->pid] = $pid;
				$data[$this->localpath] = $abs;
				return $this->dao->add($data);
			} else {
				return false;
			}
		}
	}

//sql语句具体的例子
//update dl_category set local_path=replace(local_path,'0-20','0-21') where local_path like '0-20-%'
	function moveNode($id, $pid)
	{
		if (0 == $id) {
			return false;
		}
		$cids = $this->getCId($id);
		//不能把自己添加到自己的子节点上。
		if (in_array($pid, $cids)) {
			return false;
		}
		$pLocalpath = $this->dao->field($this->localpath)->where('id=' . $pid)->getField();
		//$pid所指节点存在
		if ($pLocalpath) {
			//移动后的节点的新的localpath就是新父id的绝对路径
			$localpath = $pLocalpath . '-' . $pid;
			$preLocalpath = $this->dao->field($this->localpath)->where('id=' . $id)->getField();
			//$id所指节点存在
			if ($preLocalpath) {
				$preAbs = $preLocalpath . '-' . $id;
				$abs = $localpath . '-' . $id;
				$this->dao->startTrans();
				$this->dao->where('id=' . $id)->save(array($this->pid => $pid, $this->localpath => $localpath));
				$affect_rows = $this->dao->execute("update{$this->tableName} set {$this->localpath}= replace({$this->localpath},'{$preAbs}','{$abs}') where {$this->localpath} like '{$preAbs}-%'");
				if ($affect_rows) {
					$this->dao->commit();
					return true;
				} else {
					$this->dao->rollback();
					return false;
				}
			}
		}
	}

	function _printSelect($tree, $name, $default=1)
	{
		$htmlStr = '<select class="text-box" name="' . $name . '" id="' . $name . '">';
		$htmlStr.='<option value="0">作为一级目录添加</option>';
		foreach ($tree as $key => $value) {
			if ($value[$this->pid] == 0) {
				$colTitle = '≮' . $value[$this->content] . '≯';
				$htmlStr .= '<option value="' . $value['id'] . '">' . $colTitle . '</option>';
			} elseif ($value['colId'] == $default) {
				$colTitle = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', count(explode('-', $value[$this->localpath])) - 1) . '→&nbsp;' . $value[$this->content];
				$htmlStr .= '<option value="' . $value['id'] . '" selected>' . $colTitle . '</option>';
			} else {
				$colTitle = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', count(explode('-', $value[$this->abspath])) - 1) . '→&nbsp;' . $value[$this->content];
				$htmlStr .= '<option value="' . $value['id'] . '">' . $colTitle . '</option>';
			}
		}
		$htmlStr .= '</select>';
		return $htmlStr;
	}

	function delNode($id)
	{
		//不能让所有的节点都删除了。
		if (0 == $id) {
			return false;
		}
		$absArr = $this->dao->field($this->localpath)->where('id=' . $id)->select();
		$abs = $absArr[0][$this->localpath];
		return $this->dao->where($this->localpath . ' like \'' . $abs . '-' . $id . '%\' or  id=' . $id)->delete();
	}

	function buildTable()
	{
		$option = $this->getTree();
		$htmlStr = '<table>';
		$htmlStr .='<tr><td></td></tr>';
		$i = 0;
		foreach ($option as $value) {
			if ($i++ % 2 == 0)
				$class = 'light-row';
			else
				$class='dark-row';
			$row = '<tr class=' . $class . '><td>' . str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;', count(explode('-', $value[$this->abspath])) - 1);
			$row.=$value[$this->content] . '</td>';
			$row.='<td><input type="button" value="删除" id="' . $value['id'] . '"/></td></tr>';
			$htmlStr.=$row;
		}
		$htmlStr.='</table>';
		return $htmlStr;
	}

	function tree2json()
	{
		
	}

}
?>
