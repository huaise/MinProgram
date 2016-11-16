Page({
  data:{
    list:[
            {
              list_tool:[
                          {
                              img:"/image/friend_r.png",
                              name:"朋友圈",
                              url:""
                            }
                          ]
            },
            {
              list_tool:[
                          {
                            img:"/image/saoyisao.png",
                            name:"扫一扫",
                            url:""
                    },
                    { 
                            img:"/image/yaoyiyao.png",
                            name:"摇一摇",
                            url:""
                    }
                ] 
            },
            {
              list_tool:[
                          {
                            img:"/image/newFriend.png",
                            name:"附近的人",
                            url:""  
                          },
                          {
                            img:"/image/piaoliuping.png",
                            name:"漂流瓶",
                            url:""
                          }

              ] 
            },
            {
              list_tool:[
                          {
                            img:"/image/gouwu.png",
                            name:"购物",
                            url:""
                          },
                          {
                            img:"/image/gouwu.png",
                            name:"游戏（现在是prcker）",
                            url:"../prcker/prcker"
                          }
              ]
            }
          ]
  },
  onLoad: function () {
     // 页面初始化 options为页面跳转所带来的参数
  },
  goPage:function(event){
    //新窗口打开
    var tourl = event.currentTarget.dataset.url
    if(tourl != ''){
      wx.navigateTo({
            url: event.currentTarget.dataset.url
        })
      }
    return false
  }
})