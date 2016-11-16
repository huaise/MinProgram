Page({
  data:{
    src:""
  },
  onLoad:function(options){
    // 页面初始化 options为页面跳转所带来的参数
  },
  bindButtonTap:function(){
    var that = this
    wx.chooseVideo({

        sourceType: ['album', 'camera'],
         maxDuration: 60,
            camera: ['front','back'],
        success:function(res){
            this.setData({
                src:res.tempFilePaths
            })
        }
    })
  },
  videoErrorCallback:function(e){
      console.log('视频错误信息')
      console.log(e.detail.errMsg)
  }
})