// 获取app实例
var app = getApp();
console.log(app);
Page({
  data:{
        img_top:'',
        img_bottom:''
  },
  onLoad:function(options){
    // 页面初始化 options为页面跳转所带来的参数
  },
  upImg:function(){
      var _self = this
      wx.chooseImage({
        count:1,
        sizeType:['original','compressed'],
        sourceType:['album', 'camera'],
        success:function(res){
            var tempFilePaths = res.tempFilePaths
            //显示临时文件
            _self.setData({
                img_top:tempFilePaths
            })
            //选择照片成功,开始上传
             wx.uploadFile({
                url:app.globalData.ip+'/WeApp_master_hail/service/api/api',
                filePath:tempFilePaths[0],
                name:'webfile',
                formData:{
                        act:'upload',
                        uploadName:app.globalData.userInfo.nickName,
                        action:'D3ZgFGyusewfetEtk'

                },
                success:function(event){
                    console.log(event);
                },
                fail:function(){
                    console.log('false')
                },
                complete:function(){
                    console.log('unknown')
                }
            })
        }
      })
  },
  dwImg:function(){
    var _self = this
    wx.downloadFile({
        url:"http://f.hiphotos.baidu.com/image/h%3D360/sign=b9a4961ad71b0ef473e89e58edc451a1/b151f8198618367ac7d2a1e92b738bd4b31ce5af.jpg",
        type:'image',
        success:function(res){
            _self.setData({
                img_bottom:res.tempFilePath
            })
        }
    })
  }
})