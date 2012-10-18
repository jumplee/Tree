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
    private $nodeName = 'name';
	//private $pName='parent_name';
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

    /**
     * 没有参数得到完整的树
     * @return array
     *
     * */
    public function getTree($id = 0)
    {
        $tree = null;
        if (0 === $id) {
            $tree = $this->dao->field('*,concat(local_path,\'-\',id) as abs_path')->order($this->abspath)->select();
            return $tree;
        } else {
            $localpath = $this->dao->where('id=' . $id)->getField($this->localpath);
            $abspath = $localpath . '-' . $id;
            $tree = $this->dao->field('*, concat(local_path,\'-\',id) as abs_path')->where($this->localpath . ' like \'' . $abspath . '%\'')->order($this->abspath)->select();
        }
        return $tree;
    }

	public function getTree2($id=0,$limit='')
	{
		$limit=$limit?' limit '.$limit:'';
		if(0==$id){
		$sql="SELECT a.*,concat(a.local_path,'-',a.id) as abs_path ,b.name as parent_name FROM cate  as a left join cate as b on a.parent_id =b.id ORDER BY abs_path ".$limit;
		}else{
			$localpath = $this->dao->where('id=' . $id)->getField($this->localpath);
            $abspath = $localpath . '-' . $id;
			$sql="SELECT a.*,concat(a.local_path,'-',a.id) as abs_path ,b.name as parent_name FROM cate  as a left join cate as b on a.parent_id =b.id where a.local_path like '{$abspath}%' ORDER BY abs_path ".$limit;
		}
		$tree=$this->dao->query($sql);
		$first_node_deep=count(explode('-', $tree[0][$this->localpath]));
		//foreach 不是引用传递而是值传递？
		//foreach($tree as $value){
		//只有这种方法可以实现引用传递
		foreach($tree as $key=>&$value){
		$value[$this->nodeName]=str_repeat('--', count(explode('-', $value[$this->localpath])) -$first_node_deep) .$value[$this->nodeName];
		}
		return $tree;
	}
	
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

    function addNode($pid = 0, $name,$other_data=array())
    {
        if (!$name) {
            return false;
        }
		if($other_data){
			$data=$other_data;
		}
		$data[$this->nodeName] = $name;
		$data[$this->pid] = $pid;
        if (0 == $pid) {
            $data[$this->localpath] = '0';
            return $this->dao->add($data);
        } else {
            $absArr = $this->dao->field($this->localpath)->where('id=' . $pid)->select();
            if ($absArr) {
                $abs = $absArr[0][$this->localpath] . '-' . $pid;
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
            return 0;
        }
        $cids = $this->getCId2($id);
        //不能把自己添加到自己的子节点和自己上。
        if (in_array($pid, $cids)) {
            return 2;
        }
        //因为根节点并不存在在数据库中，所以要取得根节点的所属路径是不可能从数据库中读出的，
        //反正求新的父节点的所属路径就是为了求节点的新的所属路径$localpath中使用，干脆到后面直接把$localpath赋值为"0"就行了
        //这里直接忽略根节点问题
        if ($pid != 0) {
           //获得新的父节点的所属路径
            $pLocalpath = $this->dao->where('id=' . $pid)->getField($this->localpath);
        } 

        //$pid所指节点存在
        //因为如果是根节点，$pLocalpath会是0；无法通过if来了解是否有这个父节点，所以使用数组来判断
        ///todo 不知道isset()能不能使用
        //已经改进；可以isset()可以区分0和null
        if (isset($pLocalpath)) {
            //移动后的节点的的所属路径就是新的父节点的绝对路径
            $localpath = $pLocalpath . '-' . $pid;
        //根节点的子类的localpath并不是0-0而是0，所以需要特殊处理
            if($pid==0){
                $localpath='0';
            }
            //获得以前的所属路径
            $preLocalpath = $this->dao->where('id=' . $id)->getField($this->localpath);
            //$id所指节点存在
            if (isset($preLocalpath)) {
                //以前的绝对路径
                //也就是以前的所有子节点的所属路径
                $preAbs = $preLocalpath . '-' . $id;
                //修改后的绝对路径
                $abs = $localpath . '-' . $id;
                $this->dao->startTrans();
                //修改节点信息
                $this->dao->where('id=' . $id)->save(array($this->pid => $pid, $this->localpath => $localpath));
                //修改子节点的信息
                $affect_rows = $this->dao->execute("update {$this->tableName} set {$this->localpath}= replace({$this->localpath},'{$preAbs}','{$abs}') where {$this->localpath} like '{$preAbs}%'");
                if ($affect_rows) {
                    $this->dao->commit();
                    return 1;
                } else {
                    $this->dao->rollback();
                    return 99;
                }
            }
        }
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

    /**
     * 	获取所有子节点的id
     * @param <type> $id
     * @return <type> array;
     */
    public function getCId($id = 0)
    {
        $tt = $this->getTree($id);
        $ids = array();
        foreach ($tt as $child) {
            $ids[] = (int) $child['id'];
        }
        return $ids;
    }

    function getArray()
    {
        $tree = $this->getTree();
        $len = count($tree);
        $tree[0]['cha'] = 0;
        for ($k = 1; $k < $len; $k++) {
            $cha = substr_count($tree[$k][$this->abspath], $this->spVar) - substr_count($tree[$k - 1][$this->abspath], $this->spVar);
            $tree[$k]['cha'] = $cha;
        }
        return $tree;
    }

    function htmlPrintAll()
    {
        //return $this->_htmlPrint($this->getTree());
        return $this->_htmlPrint($this->getArray());
    }

    /**
     *
     * @param type $name  select标签的名字
     * @param $default      默认的选项
     * @return type 
     */
    function Select2($name, $styleClass,$first,$default = 0)
    {
        return $this->_printSelect($this->getTree(), $name ,$styleClass,$first,$default);
    }

    function Select($name,$styleClass="",$default=0)
    {
        return $this->_printSelect($this->getTree(), $name ,$styleClass,$first='',$default);
    /* 
        if($styleClass){
			$styleClass =' class="'.$styleClass.'"';
		}
        $htmlStr = '<select name="' . $name . '" id="' . $name . '"'.$styleClass.'>';
        $tree = $this->getTree();
        foreach ($tree as $key => $value) {
            if ($value[$this->pid] == 0) {
                $colTitle =$value[$this->nodeName];
                $htmlStr .= '<option value="' . $value['id'] . '">' . $colTitle . '</option>';
            } elseif ($value['id'] == $default) {
                $colTitle = str_repeat('—', count(explode('-', $value[$this->localpath])) - 1)  . $value[$this->nodeName];
                $htmlStr .= '<option value="' . $value['id'] . '" selected>' . $colTitle . '</option>';
            } else {
                $colTitle = str_repeat('—', count(explode('-', $value[$this->abspath])) - 1) . $value[$this->nodeName];
                $htmlStr .= '<option value="' . $value['id'] . '">' . $colTitle . '</option>';
            }
        }
        $htmlStr .= '</select>';
        return $htmlStr; 
        */
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
        $len = count($tree);
        for ($k = 0; $k < $len; $k++) {
            //$cha = substr_count($tree[$k][$this->abspath], $this->spVar) - substr_count($tree[$k - 1][$this->abspath], $this->spVar);
            $cha = $tree[$k]['cha'];
            $block = '<span><a href="/qingdao/index.php/product/index/cate_id/' . $tree[$k]['id'] . '" id="' . $tree[$k]['id'] . '">' . $tree[$k]['name'] . '</a></span>';
            //  $block2 = '<a href="' . $tree[$k]['id'] . '">' . '&nbsp&nbsp' . $tree[$k]['name'] . '</a>';
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
     *
     * @param type $tree
     * @param type $name
     * @param type $default   设置
     * @return string 
     */
    function _printSelect($tree, $name, $styleClass='',$first='',$default = 0)
    {
		if($styleClass){
			$styleClass =' class="'.$styleClass.'"';
		}
        $htmlStr = '<select name="' . $name . '" id="' . $name . '"'.$styleClass.'>';
		if($first){
			$first='<option value="0">'.$first.'</option>';
		}
        $htmlStr.=$first;
        foreach ($tree as $key => $value) {
            if ($value[$this->pid] == 0) {
                $colTitle = $value[$this->nodeName];
                if ($value['id'] == $default) {
                    $htmlStr .= '<option value="' . $value['id'] . '">' . $colTitle . '</option>';
                } else {
                    $htmlStr .= '<option value="' . $value['id'] . '">' . $colTitle . '</option>';
                }
            } elseif ($value['id'] == $default) {
                $colTitle = str_repeat('--', count(explode('-', $value[$this->localpath])) - 2) .$value[$this->nodeName];
                $htmlStr .= '<option value="' . $value['id'] . '" selected>' . $colTitle . '</option>';
            } else {
                $colTitle = str_repeat('--', count(explode('-', $value[$this->abspath])) - 2) . $value[$this->nodeName];
                $htmlStr .= '<option value="' . $value['id'] . '">' . $colTitle . '</option>';
            }
        }
        $htmlStr .= '</select>';
        return $htmlStr;
    }

    function buildTable()
    {
        $option = $this->getTree();
        $htmlStr = '<table>';
        $htmlStr .='<tr><td class="tab1">名称</td><td class="tab2">操作</td></tr>';
        $i = 0;
        foreach ($option as $value) {
            if ($i++ % 2 == 0)
                $class = 'light-row';
            else
                $class = 'dark-row';
            $row = '<tr class=' . $class . '><td>' . str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;', count(explode('-', $value[$this->abspath])) - 1);
            $row.='<span>' . $value[$this->nodeName] . '</span></td>';
            $row.='<td><input type="button" onclick="del(this)" value="删除" id="' . $value['id'] . '" class="thickbox" alt="#TB_inline?height=300&width=400&inlineId=del_cate"/>&nbsp;<input type="button" value="修改" onclick="modify(this)" alt="' . $value[$this->pid] . '" /></td></tr>';
            // $row.='<td><a id="17" class="thickbox" onclick="del(this)" href="#TB_inline?inlineId=del_panel&height=100&&width=300&title=true">删除</a></td>';
            $htmlStr.=$row;
        }
        $htmlStr.='</table>';
        return $htmlStr;
    }

    function tree2json()
    {
        
    }

    function renameNode($id, $name,$other_data)
    {
		if($other_data){
			$data=$other_data;
		}
		$data['id']=$id;
		$data[$this->nodeName]=$name;
        if ($this->dao->save($data)) {
            return true;
        }
        return false;
    }
/*	
	function rena($id,$name)
	{
		$this->dao->startTrans();
		$isNamed=$this->dao->save(array('id' => $id, $this->nodeName => $name));
		$isParentNamed =$this->dao->save(array($this->pid=>$id,$this->pName=>$name));
		if($isNamed&&$isParentNamed){
			$this->dao->commit();
		}else{
			$this->dao->rollback();
		}
	}
*/	
    /**
     *    返回的数组中包含了本身的id和子id
     * @param type $id 
     */
    function getCId2($id)
    {
        //注释：为什么要用(int)$id作为参数
        //确保$id数组都是int类型，为了使用in_array()时不出现“Wrong datatype for second argument”错误
        //因为一般传入的都是(int)$id之类的int值，而getCId是从数据库中读的值，在函数中用(int)转换过了
        $cids = $this->getCId($id);
        array_push($cids, (int) $id);
        return $cids;
    }

}

?>
