该项目为CodeIgniter的一个模板项目，在CodeIgniter的基础上面，拓展了一些核心类、辅助类库和辅助函数，并且集成了基本的管理后台。
开发前，请先阅读CodeIgniter教程：http://codeigniter.org.cn/user_guide/
以及BootStrap教程：http://v3.bootcss.com/getting-started/ 和 http://www.runoob.com/bootstrap/bootstrap-tutorial.html

下面是注意事项，开发前请务必阅读：

· 正式项目中，请将该文件（readme.txt）移除

· 使用前，请将application/config/template/下面的config.php和database.php复制到application/config/development/下面，并根据本机情况进行相应的修改。
application/config/development/下面的配置项，不建议进入代码库，以方便不同开发者根据不同的本地环境进行配置
application/config/production/下面的配置项，不建议进入代码库，以保护生产环境的各种密钥

· 默认的前台、后台和接口目录名分别为home、admin和api，如果要修改，比如想使用一个更安全的名称来替代admin以避免被猜到管理路径，请按照以下步骤修改：
① 修改application/config/constants.php中的HOMEDIR、ADMINDIR或者APIDIR
② 修改application/controller下面的子目录名称
③ 修改application/views下面的子目录名称
④ 修改assets/css、assets/img、assets/js下面的子目录名称

· 为了安全起见，建议修改Superadmin_model中get_superadmin_password_seed和get_superadmin_token_seed的盐值
修改之后，需要重新生成数据库中admin用户的密码，然后直接改数据库中的密码字段
方法为：
password_hash($this->superadmin_model->get_superadmin_password_seed($data['password']), PASSWORD_DEFAULT);

· php输出任何路径的时候，不要直接写死字符串，请使用以下方法：
项目根路径：config_item('base_url')，如http://localhost/CodeIgniter/
静态文件根路径：config_item('static_url')，如http://localhost/CodeIgniter/assets/
前台、后台和接口目录名：HOMEDIR、ADMINDIR或者APIDIR

以上路径的拼接即可以满足大部分输出路径的要求，例如：
到某个控制器的路径：config_item('base_url').ADMINDIR."login"
访问后台的某个js：config_item('static_url')."js/".ADMINDIR."sample.js"
load前台的某个view：$this->load->view(HOMEDIR.'sampleview');

· codeigniter.sql为数据库文件，包含了管理员数据，供参考。管理员用户名为admin，密码为codeigniter。正式项目中请将sql文件从项目中移除

· MY_Model类封装了基本插入、查找、更新、删除方法，新建的model应当继承MY_Model类。Superadmin_model是一个简单的示例

· sql语句的注意事项
1、查询的时候，尽量使用查询构建器，且where方法不要传入自定义字符串，自定义字符串是不会被转义的。
参考：http://codeigniter.org.cn/user_guide/database/query_builder.html
2、如果要使用原生sql，请使用以下方法进行转义：
方法1：使用escape系列函数
    例如：
        $sql = "INSERT INTO table (title) VALUES(".$this->db->escape($title).")";       // 会将$title转义
        $this->db->query($sql);
    再例如：
        $search = '20% raise';
        $sql = "SELECT id FROM table WHERE column LIKE '%" . $this->db->escape_like_str($search)."%'";  // 会将$search转义，用于处理 LIKE 语句中的字符串
        $this->db->query($sql);

方法2：使用查询绑定：
    例如：
        $sql = "SELECT * FROM some_table WHERE id = ? AND status = ? AND author = ?";
        $this->db->query($sql, array(3, 'live', 'Rick'));
    再例如：
        $sql = "SELECT * FROM some_table WHERE id IN ? AND status = ? AND author = ?";
        $this->db->query($sql, array(array(3, 6), 'live', 'Rick'));


· 获取get、post、server、session的时候，一律使用$this->input->get('***')和$this->input->post('***')等方法

· 类中的所有成员变量，均应该以下划线开头

· 新增了model或者service，需要在auto_helper/my_models.php的头注释部分中也添加一下，以支持自动补全和跳转

· 拓展/新增了核心类（core）或者类库（library），需要在auto_helper/CI_phpStorm.php中的头注释部分中也修改/添加一下，以支持自动补全和跳转

· 如果辅助函数有重复定义的情况出现，则需要参考MY_开头的文件中的定义，此时不会调用系统的辅助函数

· 超级后台每个view的js一般可以分成两部分：

①该view特有的js，基本不会被复用，一般放在config_item('static_url')."js/".ADMINDIR."控制器名称/方法名称.js"中
如assets/js/admin/manage/edit.js
②一些共同的js，比如封装好的一些类库，一般放在config_item('static_url')."js/".ADMINDIR."文件夹名称/文件名称.js"中
如assets/js/admin/util/bootstrap-datepicker.min.js

js文件会在加载footer的时候，自动引用，如：
$footer_data['js'][] = config_item('static_url')."js/".ADMINDIR."util/bootstrap-datepicker.min.js";                     // 加载公共的js
$footer_data['js'][] = config_item('static_url')."js/".ADMINDIR.$this->router->class."/".$this->router->method.".js";   // 加载特有的js
$this->load->view(ADMINDIR.'common/footer', $footer_data);

js以此种方法加载的时候，会自动加上版本号，避免浏览器缓存，版本号定义在/application/config/constants.php中，如JS_VERSION_ADMIN

·  超级后台的共同的css放在header.php中，如果某个view要加载特殊的css，请在加载footer的时候加载，和js的加载基本一样：
$footer_data['css'][] = config_item('static_url')."css/".ADMINDIR."bootstrap-datepicker3.min.css";

· 为了尽可能复用视图，超级后台的添加和修改一般不分离，具体做法如下：
 控制器中的方法名称、view名称和js文件名，统一叫做edit
 控制器通过是否传入id等参数，判断是添加还是修改，通过是否post数据，判断是否是提交操作，进而进入不同的逻辑中
 控制器中封装一些private的函数，来处理显示逻辑，这样可以方便调用，这些private的函数需要以下划线开头
 显示的时候，需要告诉视图是添加还是修改，以便于视图中进行一些不同的显示操作
 视图中根据是添加还是修改，进行不同的操作，例如：
  某项只在添加的时候可以输入，修改的时候只显示数据不允许输入（比如注册邮箱）
  某项在添加的时候不显示，在编辑的时候显示（如添加时间字段）
  表单的提交按钮的value不同，方便控制器进行判断，增加鲁棒性
 表单中的数据有以下几种情况：
    A、添加时，第一次进入页面，应该显示空或者是默认值
    B、添加时，表单提交之后但是没有通过后台表单验证，则应该保留用户刚刚填过的值，避免再次填写
    C、修改时，第一次进入页面，应该显示从数据库取出的值
    D、修改时，表单提交之后但是没有通过后台表单验证，则应该保留用户刚刚填过的值，避免再次填写
 为了满足以上需求且能将添加和修改视图合并在一起，需要使用拓展的表单验证辅助函数set_value,set_select,set_checkbox和set_radio等。如用户名输入框：
    <input type="text" id="username" name="username"
    value="<?php echo set_value('username', isset($superadmin['username'])?$superadmin['username']:'');?>">
    如权限选择框：
    <select id="authority" name="authority">
        <option value="0" <?php echo  set_select('authority', 0, isset($superadmin['authority'])?$superadmin['authority']:NULL); ?>>超级管理员</option>
        <option value="1" <?php echo  set_select('authority', 1, isset($superadmin['authority'])?$superadmin['authority']:NULL, TRUE); ?>>普通管理员</option>
        <option value="99" <?php echo  set_select('authority', 99, isset($superadmin['authority'])?$superadmin['authority']:NULL); ?>>客服</option>
    </select>
 这样，如果启用了表单验证，会先使用表单或者POST中的数据（对应情况B、D），否则如果有数据库中取出的值(又称为候补值，这里为$superadmin中的字段，需要在控制器中赋值给视图)，则使用数据库中取出的值（对应情况C），否则就显示默认值（对应情况A）
 注意，由于辅助函数采用了严格比较，因此三目运算符中的最后一项，应该为对应函数的对应参数的默认值，如set_value中应为''，set_select,set_checkbox和set_radio应为NULL等

 具体代码可以参见管理员的添加和修改逻辑

· 前端表单验证统一使用BootstrapValidator，请参考：http://bv.doc.javake.cn
  后端表单验证使用CI的Form_validation类库，请参考：http://codeigniter.org.cn/user_guide/libraries/form_validation.html
  前后端表单验证逻辑要一致

· 用户的操作是一个更加完善的例子，包括
  ①用户的model，即User_model，对应两个表，p_userbase和p_userdetail，User_model展示了当一个模型对应多个表的时候的示例代码
  ②某些字段，只在编辑时展现：token，constellation，register_time等
  ③加入了前端和后端的表单验证，如：用户必须具有唯一且合法的手机号；生日可以为空，但如果填写，就必须要满足YYYY-MM-DD的格式要求
  ④集成了日期选择控件，参考：https://github.com/eternicode/bootstrap-datepicker，可以直接在线生成代码：http://eternicode.github.io/bootstrap-datepicker/?markup=input&format=&weekStart=&startDate=&endDate=&startView=0&minViewMode=0&maxViewMode=2&todayBtn=false&clearBtn=false&language=en&orientation=auto&multidate=&multidateSeparator=&keyboardNavigation=on&forceParse=on#sandbox
  ⑤集成了省市区选择的三级联动，可以退化成二级联动，也可以支持其他数据源的三级联动或者同一数据源的多个三级联动。具体用法请参考添加/编辑用户页面中province、city和district的用法
  ⑥集成了上传单图并支持预览的控件。具体用法请参考添加/编辑用户页面中头像（avatar）的用法
  ⑥集成了上传多图并支持预览的控件。具体用法请参考添加/编辑用户页面中头像（album）的用法

· 目前发送短信仅支持畅卓平台，如果需要发送短信，请先申请畅卓账号：http://www.chanzor.com/
然后在/application/config/constants.php中配置CHANZOR_ACCOUNT、CHANZOR_PASSWORD、CHANZOR_SIGN
如果要使用营销短信，还需要配置CHANZOR_ACCOUNT_YX和CHANZOR_PASSWORD_YX
如果不需要发送短信，可以删除Sms.php和constants.php相应的配置

· 推送的说明



